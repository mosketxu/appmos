<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sesión caducada</title>
    <style>
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; background:#f7fafc; font-family:system-ui,Segoe UI,Arial,sans-serif; color:#374151; }
        .caja { text-align:center; padding:2rem; max-width:420px; }
        h1 { font-size:1.4rem; margin:0 0 .5rem; }
        p { margin:0 0 1.25rem; color:#6b7280; }
        a { display:inline-block; padding:.6rem 1.4rem; background:#4f46e5; color:#fff; border-radius:.5rem; text-decoration:none; font-weight:600; }
        a:hover { background:#4338ca; }
        small { display:block; margin-top:1.5rem; color:#9ca3af; }
    </style>
</head>
<body>
    <div class="caja">
        <h1>La página ha caducado</h1>
        <p>Ha pasado mucho tiempo desde que se abrió y la sesión ya no es válida. Vuelve a cargarla y repite lo que estabas haciendo.</p>
        <a href="{{ url('/') }}">Recargar y volver a entrar</a>
        <small>Error 419 · página caducada</small>
    </div>
</body>
</html>
