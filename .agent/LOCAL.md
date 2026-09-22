# Local cheat sheet

Do not treat this as production. This machine only.

## WordPress (XAMPP)

- URL: `http://testlab.ir` (or the ngrok HTTPS URL while the tunnel is up)
- Username: `admin`
- Password: `admin`

## ngrok

Apache is on port **80**. Start in PowerShell and leave the window open:

```powershell
ngrok http 80
```

Inspector: `http://127.0.0.1:4040`

Last known public URL (changes every time you restart the free tunnel): `https://deepness-subprime-stew.ngrok-free.dev`

WordPress is in a subdirectory: `http://localhost/testlab.ir` — not the Apache document root. `ngrok http 80` exposes `htdocs`, so the webhook path is:

`https://<ngrok>/testlab.ir/wp-json/storelink/v1/webhooks/telegram`

Paste only the ngrok origin into Public webhook base URL. StoreLink appends `/testlab.ir` from the site URL. After changing ngrok, click Connect Telegram webhook again.
