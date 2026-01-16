# Reglas de Negocio y Automatizaciones - Ignia Fungi ERP

Este documento es la **Fuente de Verdad Única** del sistema. Cada regla descrita aquí está garantizada por una o más pruebas automatizadas (`tests/`). Si se modifica el comportamiento del sistema, este documento y las pruebas correspondientes deben actualizarse.

---

## 1. Módulo de Producción (Batches)

### Integridad de Pesaje (Reglas Anti-Dislexia)
Previene errores humanos catastróficos al ingresar datos manuales.

| Regla | Lógica Técnica | Consecuencia | Origen (Test) |
| :--- | :--- | :--- | :--- |
| **Límite Máximo Lote** | `Batch::weigth_dry <= 50.0 kg` | Exception `ValidationException` (Bloqueo Total) | `BatchWeightLimitsTest.php` <br> `WeightHandbrakeTest.php` |
| **Alerta Preventiva Lote** | `35kg < weigth_dry < 50kg` | Advertencia Visual (Frontend/Filament) | `BatchForm` (UI Logic) |
| **Límite Máximo Cosecha** | `Harvest::weight <= 5.0 kg` | Exception `ValidationException` | `HarvestEnhancementTest.php` <br> `WeightHandbrakeTest.php` |
| **Finalización Automática** | `Batch::quantity == 0` | Estado cambia a `finalized` y se agrega observación | `BatchTest.php` |

### Trazabilidad y Transiciones
Reglas para mover un lote a través de su ciclo de vida.

| Regla | Lógica Técnica | Consecuencia | Origen (Test) |
| :--- | :--- | :--- | :--- |
| **Inoculación Requiere Genética** | Para fase `Inoculación`, `strain_id` NO puede ser null | Bloqueo de transición en Kanban y Formulario | `BatchTransitionRuleTest.php` <br> `KanbanModalTest.php` |
| **Fecha Inoculación Obligatoria** | Si existe `strain_id`, `inoculation_date` es required | Error de validación al guardar | `BatchInoculationDateTest.php` |
| **Generación de Código** | Formato: `[CEPA/SUB]-AAMMDD-XX` | Se autogenera al crear. Prefijo cambia si se asigna cepa. | `BatchTest.php` |
| **Sincronización Total** | `BatchForm` y `BatchKanban` | Cambiar fase en uno actualiza el otro y cierra la fase previa en historial | `BatchSyncTest.php` |
| **Consumo de Semilla** | Al `Sembrar en Sustrato`, lote grano pasa a `seeded` y se crea hijo | Baja de inventario de grano, creación de lote sustrato | `BatchesTable` (Action) |

### Costos e Insumos

| Regla | Lógica Técnica | Origen (Test) |
| :--- | :--- | :--- |
| **Descuento de Inventario** | Basado en Receta (`percentage` o `fixed`). <br> `Húmedo = Seco / %MateriaSeca` | `BatchTest::it_decrements_supply...` <br> `SupplyDiscountTest.php` |
| **Eficiencia Biológica** | `(∑ Peso Cosechas / Peso Seco Lote) * 100` | `BatchTest::it_calculates_biological...` |

---

## 2. Módulo de Inventario y Ventas

### Transformación de Producto

| Regla | Lógica Técnica | Consecuencia | Origen (Test) |
| :--- | :--- | :--- | :--- |
| **Deshidratación Segregada** | Una cosecha de lote (`Batch`) aumenta stock de producto **Fresco**, nunca del Deshidratado. | Stock segregado por categoría | `HarvestTest.php` <br> `ProductTest.php` |
| **Transformación Manual** | Acción `deshidratar`: Resta Fresco -> Suma Seco (ratio configurable) | Control de inventario cruzado | `ProductTest.php` |

### Preventas (Reservas)

| Regla | Lógica Técnica | Consecuencia | Origen (Test) |
| :--- | :--- | :--- | :--- |
| **Bloqueo de Capacidad** | Solo órdenes con `status: paid` reservan rendimiento futuro. | Órdenes pendientes son ignoradas para cálculo de disponibilidad | `HarvestReservationTest.php` |
| **Fecha Real de Entrega** | Se muestra `Batch::estimated_harvest_date` del lote asignado al producto | Cliente ve fecha real, no genérica | `HarvestReservationTest.php` |

### Cupones y Promociones

| Regla | Lógica Técnica | Consecuencia | Origen (Test) |
| :--- | :--- | :--- | :--- |
| **Un Cupón por Vida** | Busca en historial de órdenes `paid/delivered` del usuario si ya usó cupón. | Bloqueo: "Ya has redimido un código anteriormente" | `OneCouponPerCustomerTest.php` |
| **Race Condition Check** | Verifica elegibilidad nuevamente al momento de `placeOrder` | Evita uso simultáneo en múltiples pestañas | `OneCouponPerCustomerTest.php` |

---

## 3. Módulo de Pagos y Envíos (Checkout)

| Regla | Lógica Técnica | Origen (Test) |
| :--- | :--- | :--- |
| **Validación de Firma (Bold)** | Webhooks deben tener header `X-Bold-Signature` válido (HMAC SHA256) | `BoldWebhookTest.php` |
| **Idempotencia de Pagos** | Procesar mismo `reference` dos veces no descuenta inventario doble | `BoldWebhookTest.php` |
| **Envío Interrapidísimo** | `15,000 + (ceil(Kg) - 1) * 5,000` | `CheckoutShippingTest.php` |
| **Envío Bogotá (Paridad)** | Fecha entrega = Siguiente Día Par >= (Hoy o Cosecha) | `CheckoutShippingTest.php` |
| **Envío Gratis** | `Subtotal >= 200,000` (En Bogotá) | `CheckoutPageTest.php` |

---

## 4. Frontend y SEO

| Regla | Lógica Técnica | Origen (Test) |
| :--- | :--- | :--- |
| **Reseñas Condicionales** | Si API Google falla o retorna 0, mostrar Banner "Déjanos tu opinión" | `HomePageReviewTest.php` |
| **Solicitud de Reseña** | Al pasar Orden a `delivered`, se loguea/envía solicitud con URL específica | `OrderReviewTest.php` |
| **Sitemap Dinámico** | Incluye automáticamente todos los productos activos y posts publicados | `SitemapTest.php` |

---
_Auditoría realizada el 15 de Enero de 2026 por Agente Antigravity._
