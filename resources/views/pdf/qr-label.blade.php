<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: sans-serif;
            text-align: center;
            border: 2px dashed #333;
            padding: 20px;
            width: 300px; /* Tamaño etiqueta */
            margin: 0 auto;
        }
        .title { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
        .meta { font-size: 12px; color: #555; margin-bottom: 15px; }
        .qr { margin: 10px 0; }
        .footer { font-size: 10px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="title">{{ $batch->code }}</div>
    <div class="meta">
        {{ $batch->strain->name }} <br>
        Inoculado: {{ $batch->inoculation_date->format('d/m/Y') }}
    </div>

    <div class="qr">
        <img src="data:image/svg+xml;base64, {{ base64_encode(QrCode::format('svg')->size(200)->generate($url)) }} ">
    </div>

    <div class="footer">
        Sistema de Gestión Micológica Ignia Fungi
    </div>
</body>
</html>