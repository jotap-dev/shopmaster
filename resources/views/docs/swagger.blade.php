<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ShopMaster API — Documentação</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui.css">
    <style>
        body { margin: 0; background: #fafafa; }
        .topbar { display: none; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui-bundle.js" crossorigin></script>
    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui-standalone-preset.js" crossorigin></script>
    <script>
        window.ui = SwaggerUIBundle({
            url: "{{ route('docs.openapi') }}",
            dom_id: '#swagger-ui',
            deepLinking: true,
            persistAuthorization: true,
            defaultModelsExpandDepth: 1,
            presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
            layout: 'StandaloneLayout',
        });
    </script>
</body>
</html>
