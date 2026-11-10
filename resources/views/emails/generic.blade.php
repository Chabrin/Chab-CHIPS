<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, Helvetica, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 32px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .header { background: #1e3a5f; padding: 22px 32px; }
        .header h1 { color: #ffffff; margin: 0; font-size: 19px; }
        .body { padding: 30px 32px; color: #374151; font-size: 15px; line-height: 1.7; }
        .footer { padding: 16px 32px; background: #f9fafb; border-top: 1px solid #e5e7eb; font-size: 12px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>{{ $brand }}</h1>
        </div>
        <div class="body">{!! $body !!}</div>
        <div class="footer">&copy; {{ date('Y') }} {{ $brand }}. All rights reserved.</div>
    </div>
</body>
</html>
