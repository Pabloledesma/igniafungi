# Manual de Operación - Ignia Fungi ERP 🍄

Bienvenido al manual de uso del sistema. Aquí encontrarás guías simples para las tareas más importantes del día a día.

## 1. Registro de Producción (Lotes)

### ⚠️ Regla de Oro: ¡Siempre en Kilos!
El sistema está diseñado para trabajar con **kilogramos (kg)**, no con gramos.
- ✅ Para registrar **500 gramos**, escribe: `0.5`
- ✅ Para registrar **2 kilos y medio**, escribe: `2.5`
- ❌ NO escribas `500` (El sistema pensará que son 500 kilos o media tonelada).

### Alertas Inteligentes
- Si escribes más de **35kg**, verás una alerta amarilla: "Atención: Límite Operativo Cercano". Esto es normal si estás produciendo un lote grande.
- Si intentas guardar más de **50kg**, el sistema **bloqueará** el guardado 🛑.

---

## 2. Tablero de Producción (Kanban)

### Moviendo Lotes
Para avanzar un lote de fase (ej. de Incubación a Fructificación), simplemente arrastra la tarjeta. El sistema siempre te pedirá confirmación.

### Reglas de Inoculación 💉
No puedes mover un lote a la columna de **Inoculación** si no le has asignado una **Cepa (Strain/Genética)**.
1. Abre el lote (clic en el código).
2. Selecciona la Cepa en el formulario.
3. Guarda.
4. Ahora sí podrás moverlo en el tablero.

---

## 3. Registro de Cosechas

### Precisión ante todo
Al igual que en los lotes, el sistema te protege de errores de dedo.
- El límite máximo por registro de cosecha es **5kg**.
- Si recogiste 20kg en un día, deberás crear varios registros (ej. 4 registros de 5kg) o revisar si realmente pesaste 20kg de una sola vez (es mucho para una sola canastilla).

---

## 4. Integración con Salesforce

La plataforma mantiene los datos de lotes sincronizados automáticamente con Salesforce. A continuación los comandos disponibles para operadores:

---

### 4.1 Importar lotes masivamente desde Salesforce

Úsalo cuando en Salesforce existan lotes (`Lote__c`) que todavía no tienen un `igniaId` y quieres traerlos a la plataforma.

```bash
php artisan salesforce:import-batches
```

**Qué hace:**
1. Consulta todos los lotes en Salesforce que no tienen `igniaId` asignado.
2. Para cada uno, crea un `Batch` local con los datos del lote.
3. Si el código del lote ya existe localmente (ej. fue creado manualmente), lo vincula directamente en lugar de duplicarlo.
4. Actualiza `igniaId` en Salesforce para que quede enlazado.

**Modo de prueba (sin crear registros):**
```bash
php artisan salesforce:import-batches --dry-run
```

---

### 4.2 Sincronizar datos computados desde Salesforce

Trae los campos calculados de Salesforce (`eficiencia biológica`, `total cosechado`, `cantidad de cosechas`) y los actualiza en los lotes locales.

```bash
# Todos los lotes
php artisan salesforce:pull-batches

# Un lote específico (despacha un Job en la queue)
php artisan salesforce:pull-batches --id=42
```

Este comando también se ejecuta automáticamente **todos los días a las 3:00 AM**.

---

### 4.3 Escuchar Platform Events en tiempo real (CometD)

Este comando inicia un proceso daemon que se mantiene conectado a Salesforce via long-polling. Cada vez que un lote cambia de estado en Salesforce, el sistema local se actualiza automáticamente — sin necesidad de polling manual.

```bash
php artisan salesforce:listen
```

**Opciones:**
| Opción | Descripción |
|--------|-------------|
| `--replay=-1` | Solo eventos nuevos a partir de que se conecta (por defecto) |
| `--replay=-2` | Reprocesa todos los eventos retenidos (últimas 72 horas) |
| `--replay=12345` | Reanuda desde un `replayId` específico |

**Cómo mantenerlo activo en producción:**
El proceso no se detiene solo. Para que sobreviva reinicios del servidor, configúralo en **Supervisor** (o equivalente):

```ini
[program:salesforce-listen]
command=php /ruta/del/proyecto/artisan salesforce:listen
autostart=true
autorestart=true
stderr_logfile=/var/log/salesforce-listen.err.log
stdout_logfile=/var/log/salesforce-listen.out.log
```

---

### 4.4 Flujo automático al crear o editar lotes/cosechas

No necesitas ejecutar ningún comando manualmente para la sincronización del día a día. Cada vez que un operador crea o modifica un lote o una cosecha en la plataforma:

1. El observer detecta el cambio.
2. Se despacha automáticamente un job en la cola (`SyncBatchToSalesforce` / `SyncHarvestToSalesforce`).
3. El job actualiza el registro en Salesforce.
4. 5 segundos después, un segundo job (`PullBatchFromSalesforce`) trae los datos computados de vuelta.

Asegúrate de que el worker de colas esté corriendo:
```bash
php artisan queue:work
```

---

## 5. Atención al Cliente: Envíos y Pagos

### ¿Por qué mi pedido llega un día par? 🚚
Si un cliente pregunta, la explicación es sencilla:
> "Nuestra logística de entrega en Bogotá está optimizada para reducir la huella de carbono 🍃. Agrupamos todas las entregas en días pares (Martes 2, Jueves 4, etc.). Así garantizamos que sus hongos lleguen frescos y cuidamos el planeta."

### Pagos Seguros 🔒
Todos los pagos electrónicos son procesados por **Bold**. Nosotros nunca almacenamos los datos de sus tarjetas.
- Si el pago es exitoso, recibirán un correo con la confirmación inmediata.
- Si usan cupón, recuérdales que es **uno solo por persona** en toda la historia. Si intentan usar otro, el sistema no los dejará.
