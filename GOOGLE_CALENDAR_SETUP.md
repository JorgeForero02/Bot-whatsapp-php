# 📅 Configuración de Google Calendar

## Paso 1: Crear Proyecto en Google Cloud Console

1. Ve a [Google Cloud Console](https://console.cloud.google.com/)
2. Crea un nuevo proyecto o selecciona uno existente
3. Habilita la **Google Calendar API**:
   - Ir a "APIs y servicios" > "Biblioteca"
   - Buscar "Google Calendar API"
   - Click en "Habilitar"

## Paso 2: Crear Credenciales OAuth 2.0

1. Ve a "APIs y servicios" > "Credenciales"
2. Click en "Crear credenciales" > "ID de cliente de OAuth 2.0"
3. Tipo de aplicación: **Aplicación web**
4. URIs de redireccionamiento autorizados:
   ```
   http://localhost:3000/oauth/callback
   ```
5. Descarga el archivo JSON con las credenciales

## Paso 3: Obtener Access Token

### Opción A: Usar OAuth Playground (Más Fácil)

1. Ve a [OAuth 2.0 Playground](https://developers.google.com/oauthplayground/)
2. Click en el ícono de configuración ⚙️ (esquina superior derecha)
3. Marca "Use your own OAuth credentials"
4. Pega tu **Client ID** y **Client Secret**
5. En "Step 1", busca y selecciona:
   ```
   https://www.googleapis.com/auth/calendar
   ```
6. Click "Authorize APIs"
7. Inicia sesión con tu cuenta de Google
8. En "Step 2", click "Exchange authorization code for tokens"
9. Copia el **Access token** generado

### Opción B: Generar Token Manualmente (Avanzado)

Crear script PHP para generar token (ver `oauth_helper.php` si necesitas ayuda)

## Paso 4: Configurar en el Bot

Agrega el token en tu archivo de configuración:

```env
GOOGLE_CALENDAR_ACCESS_TOKEN=tu_access_token_aqui
GOOGLE_CALENDAR_ID=primary
```

O directamente en `config/config.php`:

```php
'google_calendar' => [
    'access_token' => 'ya29.a0AfH6SMC...',
    'calendar_id' => 'primary'
]
```

## Comandos de WhatsApp

El bot detectará automáticamente:

### Listar Eventos
- "¿Qué eventos tengo?"
- "Muéstrame mi agenda"
- "¿Qué tengo agendado?"

### Consultar Disponibilidad
- "¿Estás disponible el lunes a las 3pm?"
- "¿Tienes tiempo el 25 de febrero?"

### Agendar Cita
- "Agendar cita para mañana a las 2pm"
- "Crear evento: Reunión con cliente - 15/03 10:00 AM"

## Notas Importantes

⚠️ **Access Token Expira**: Los access tokens de Google expiran después de 1 hora
✅ **Refresh Token**: Para producción, necesitas implementar refresh token
🔒 **Permisos**: Asegúrate de dar permisos de calendario a la cuenta

## Troubleshooting

**Error 401 Unauthorized**
- Verifica que el access token sea correcto
- Genera un nuevo token si expiró

**Error 403 Forbidden**
- Verifica que la API esté habilitada
- Verifica los permisos del calendario

**No se crean eventos**
- Verifica la zona horaria
- Verifica el formato de fecha (ISO 8601)
