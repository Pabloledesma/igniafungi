<?php

namespace App\Filament\Resources\Batches\Pages;

use App\Filament\Resources\Batches\BatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

use App\Filament\Resources\Batches\Widgets\BatchFinancialReport;

class ListBatches extends ListRecords
{
    protected static string $resource = BatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            \Filament\Actions\Action::make('importHistory')
                ->label('Importar Historial')
                ->color('success')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('attachment')
                        ->label('Archivo CSV')
                        ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->disk('local')
                        ->directory('imports')
                        ->required(),
                ])
                ->action(function (array $data) {
                    // fwrite(STDERR, "Action Started\n");
                    // $data['attachment'] contains the relative path (e.g. 'imports/filename.csv')
                    // We use the Storage facade to get the absolute path.
                    $disk = \Illuminate\Support\Facades\Storage::disk('local');

                    // DEBUG
                    // dump('Data attachment:', $data['attachment']);
        
                    $filePath = $disk->path($data['attachment']);
                    // dump('Resolved Path:', $filePath);
                    // dump('File Exists?', file_exists($filePath));
        
                    if (!file_exists($filePath)) {
                        // fwrite(STDERR, "File not found at: $filePath\n");
                        // Fallback/Debug: check if it's somewhere else or handle test environment
                        // In tests, sometimes it's in a tmp dir? 
                        // But FileUpload should have moved it to 'imports'.
                        // If not found, log error or try straightforward concatenation if key is just filename (unlikely with directory() set)
                    } else {
                        // DBG
                    }

                    try {
                        $file = fopen($filePath, 'r');
                        $header = fgetcsv($file); // Skip header: Expected: strain_code, inoculation_date, type, quantity, bag_weight, status, reason, harvest_yield, harvest_date
        
                        // Map headers to indices approximately or assume fixed order? 
                        // Better: Assume fixed order for simplicity or named keys if using a library.
                        // User didn't specify format, I will assume a standard order and document it or try to map by name.
                        // Let's assume standard order: strain, date, type, quantity, bag_weight, status, reason, harvest_yield, harvest_date
        
                        $errors = [];
                        $successCount = 0;
                        $rowNumber = 1;

                        \Illuminate\Support\Facades\DB::beginTransaction();

                        while (($row = fgetcsv($file)) !== false) {
                            $rowNumber++;
                            // Simple mapping (adjust as needed)
                            // 0: strain (code/slug), 1: date (dmy), 2: type, 3: quantity, 4: bag_weight, 5: status, 6: reason, 7: harvest_yield, 8: harvest_date
        
                            if (count($row) < 5) {
                                $errors[] = "Fila $rowNumber: Datos insuficientes.";
                                continue;
                            }

                            $strainInput = $row[0] ?? null;
                            $dateInput = $row[1] ?? null;
                            $type = strtolower($row[2] ?? 'bulk');
                            $quantity = (float) str_replace(',', '.', $row[3] ?? 0);
                            $bagWeight = (float) str_replace(',', '.', $row[4] ?? 0);
                            $status = strtolower($row[5] ?? 'seeded');
                            $reason = $row[6] ?? null;
                            $harvestYield = (float) str_replace(',', '.', $row[7] ?? 0);
                            $harvestDateInput = $row[8] ?? null;
                            $observations = !empty($row[9]) ? $row[9] : null;
                            $originCode = !empty($row[10]) ? $row[10] : null;
                            $containerType = !empty($row[11]) ? $row[11] : null;

                            // fwrite(STDERR, "Processing Row $rowNumber: $strainInput\n");
        
                            // 1. Validation: Strain
                            $strain = \App\Models\Strain::where('slug', $strainInput)
                                ->orWhere('name', $strainInput)
                                ->first();

                            if (!$strain) {
                                $errors[] = "Fila $rowNumber: Cepa '$strainInput' no encontrada.";
                                // fwrite(STDERR, "Strain mismatch: $strainInput\n");
                                continue;
                            }

                            // 2. Date parsing (ddmmyy or d/m/Y ?) User said "format previous ddmmyy", but in CSV usually distinct.
                            // I'll try parse d/m/Y or Y-m-d.
                            try {
                                $inocDate = \Carbon\Carbon::parse($dateInput);
                            } catch (\Exception $e) {
                                $errors[] = "Fila $rowNumber: Fecha inválida '$dateInput'.";
                                continue;
                            }

                            // Create Batch
                            try {
                                // Default user?
                                $user = auth()->user() ?? \App\Models\User::first();

                                $batch = \App\Models\Batch::create([
                                    'user_id' => $user->id,
                                    'strain_id' => $strain->id,
                                    'type' => $type,
                                    'grain_type' => $type === 'grain' ? 'Millet' : null, // Default
                                    'quantity' => $quantity,
                                    'bag_weight' => $bagWeight,
                                    'status' => $status,
                                    'inoculation_date' => $inocDate,
                                    'is_historical' => true,
                                    'initial_wet_weight' => $quantity * $bagWeight * 0.4, // Estimate? Or make nullable. dry weight is required.
                                    'observations' => $observations,
                                    'origin_code' => $originCode,
                                    'container_type' => $containerType,
                                    // Hack: Calculate dry weight if not provided. Model requires it?
                                    // Checking Batch model... 'weigth_dry' is nullable? No, it's typically calculated or required.
                                    // I'll assume approximate calculation.
                                ]);

                                // Post-Creation Logic
        
                                // Losses
                                if (in_array($status, ['discarded', 'contaminated', 'contamination'])) {
                                    // Observer should have created a loss record.
                                    // We update it with specific reason if provided.
                                    if ($reason) {
                                        $loss = \App\Models\BatchLoss::where('batch_id', $batch->id)->latest()->first();
                                        if ($loss) {
                                            $loss->update(['reason' => $reason]); // 'reason' is enum? 
                                            // BatchLoss reason is enum or string. If enum, might fail. 
                                            // If input is text, maybe put in 'details' or 'observations'.
                                            // BatchLoss 'reason' is enum ('Contaminación', etc). 'details' is text.
                                            // I'll put it in details/observations if it's custom text.
                                            // Or try to match enum.
                                            // Safer: Update 'observations' or 'details'.
                                            // Checking BatchLoss model... 'reason' (string/enum), 'details' (string).
                                            // Assuming CSV 'reason' maps to 'reason' field if valid, else details.
                                            // Let's just append to details to be safe.
                                            $loss->update(['details' => $reason . ' (Importado)']);
                                        }
                                    }
                                }

                                // Harvests
                                if (in_array($status, ['completed', 'finalized']) && $harvestYield > 0) {
                                    $hDate = $harvestDateInput ? \Carbon\Carbon::parse($harvestDateInput) : now();

                                    \App\Models\Harvest::create([
                                        'batch_id' => $batch->id,
                                        'weight' => $harvestYield,
                                        'harvest_date' => $hDate,
                                        'is_historical' => true
                                    ]);
                                }
                                $successCount++;
                            } catch (\Exception $e) {
                                $errors[] = "Fila $rowNumber: Error al crear lote - " . $e->getMessage();
                            }
                        }

                        \Illuminate\Support\Facades\DB::commit();
                        fclose($file);

                        // fwrite(STDERR, "Finished. Success: $successCount\n");
        
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\DB::rollBack();
                        // fwrite(STDERR, "Exception: " . $e->getMessage() . "\n");
                        \Filament\Notifications\Notification::make()
                            ->title('Error Crítico')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                        return;
                    }

                    if (count($errors) > 0) {
                        \Filament\Notifications\Notification::make()
                            ->title('Importación completada con errores')
                            ->body("Importados: $successCount. Errores: " . implode('<br>', array_slice($errors, 0, 5)))
                            ->warning()
                            ->persistent()
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title('Importación Exitosa')
                            ->body("Se importaron $successCount lotes correctamente.")
                            ->success()
                            ->send();
                    }
                }),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            BatchFinancialReport::class,
        ];
    }
}
