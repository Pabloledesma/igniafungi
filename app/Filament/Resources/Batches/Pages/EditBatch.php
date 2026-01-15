<?php

namespace App\Filament\Resources\Batches\Pages;

use App\Filament\Resources\Batches\BatchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBatch extends EditRecord
{
    protected static string $resource = BatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Forzamos la carga de la fase actual REAL de la base de datos
        // $record es el modelo Batch
        $record = $this->getRecord();

        // Usamos el accessor o la relación directa
        $currentPhase = $record->phases()->wherePivot('finished_at', null)->first();

        if ($currentPhase) {
            $data['phase_id'] = $currentPhase->id;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        // Obtenemos los datos del formulario directamente
        $data = $this->data;

        if (isset($data['phase_id'])) {
            $newPhaseId = $data['phase_id'];
            $currentPhaseId = $record->current_phase?->id; // Usamos el accessor del modelo

            if ($newPhaseId != $currentPhaseId) {
                $newPhase = \App\Models\Phase::find($newPhaseId);

                if ($newPhase) {
                    // Ejecutamos la transición usando el método del modelo
                    // Esto cerrará la fase anterior y abrirá la nueva
                    $record->transitionTo($newPhase, 'Cambio manual de fase desde Admin Panel');

                    // Notificamos al usuario
                    \Filament\Notifications\Notification::make()
                        ->title('Fase actualizada')
                        ->body("El lote se ha movido a la fase: {$newPhase->name}")
                        ->success()
                        ->send();
                }
            }
        }
    }
}
