<div style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; color: #1f2d3d; line-height: 1.5; max-width: 560px;">
    <p>Hello {{ $name }},</p>
    <p>An account has been created for you on {{ config('app.name', 'CHIPS') }}. Use the button below to choose your own password. Nobody else sees or knows it.</p>
    <p style="margin: 24px 0;">
        <a href="{{ $url }}" style="background: #3598db; color: #ffffff; text-decoration: none; padding: 12px 22px; border-radius: 4px; display: inline-block;">Set my password</a>
    </p>
    <p>Your login email is <strong>{{ $email }}</strong>.</p>
    <p>The link works once and stops working after {{ $hours }} hours. If it has expired, open it and choose "Send me a new link", or ask HR to send a new one.</p>
    <p style="color: #5b6b7c; font-size: 13px;">If the button does not work, copy this address into your browser:<br>{{ $url }}</p>
    <p style="color: #5b6b7c; font-size: 13px;">If you were not expecting this email, please ignore it and tell your HR team.</p>
</div>
