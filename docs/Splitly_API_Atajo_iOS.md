# Splitly API + Atajo de iOS

Esta integración permite registrar gastos en Splitly directamente desde
Atajos o Siri, sin guardar la contraseña de la cuenta y sin mantener
manualmente categorías, grupos o participantes dentro del Atajo.

El Atajo obtiene las opciones directamente desde la API de Splitly y
utiliza un token personal revocable para autenticar las peticiones.

------------------------------------------------------------------------

## 1. Configuración inicial del token

El token solo debe introducirse la primera vez que se configura el Atajo
en un dispositivo.

### Generar el token

1.  Abre **Splitly → Configuración → Atajos de iOS**.
2.  Pulsa **Generar token**.
3.  Copia el token generado.
4.  El token solo se muestra una vez.

Ejemplo:


``` text
spl_ios_...
```

### Guardar el token desde el Atajo

Al comienzo del Atajo añade:

1.  **Obtener archivo**
    -   Carpeta: `Shortcuts`
    -   Ruta: `Splitly/token.txt`
    -   Desactivar **Error si no se encuentra**.
2.  **Obtener texto de \[Archivo\]**
    -   La entrada debe ser la variable mágica del archivo obtenido
        anteriormente.
3.  Añade una condición: **Si \[Texto\] no tiene ningún valor**.

Dentro de **Si**:

-   **Solicitar entrada**
    -   Tipo: Texto
    -   Pregunta: `Pega tu token de Splitly`
-   **Guardar archivo** en `Shortcuts/Splitly/token.txt`.
-   **Definir variable**: `Token Splitly = Solicitar entrada`.

Dentro de **Si no**:

`Token Splitly = Texto`

El flujo queda:

``` text
Obtener token.txt
        ↓
Obtener texto del archivo
        ↓
¿Existe token?
   ├─ NO → Pedir token → Guardar token.txt → Token Splitly
   └─ SÍ → Token Splitly = contenido de token.txt
```

De esta forma el token se solicita únicamente durante la configuración
inicial.

> iOS puede solicitar permisos para acceder a archivos o conectarse al
> dominio de Splitly durante las primeras ejecuciones. Estos permisos
> son independientes del token.

### Autenticación

Todas las llamadas a la API utilizan:

``` http
Authorization: Bearer [Token Splitly]
```

`Token Splitly` debe insertarse como **variable mágica**, no escribirse
literalmente.

El token identifica al usuario de Splitly, permite acceder únicamente a
los recursos autorizados para esa cuenta y puede revocarse desde
Configuración.

------------------------------------------------------------------------

## 2. Cargar categorías y grupos automáticamente

Después de obtener `Token Splitly`, añade **Obtener contenido de URL**:

``` http
GET https://TU_DOMINIO/api/shortcuts/options.php
Authorization: Bearer [Token Splitly]
```

Guarda el resultado como `Opciones Splitly`.

La API devuelve:

-   `categories`: categorías disponibles.
-   `groups`: grupos del usuario.
-   `payers`: participantes del grupo seleccionado.
-   `group_details`: información adicional de los grupos.

No es necesario escribir manualmente categorías, grupos o participantes
en el Atajo.

------------------------------------------------------------------------

## 3. Solicitar el importe

El importe debe solicitarse como **Texto**, no como Número. Esto evita
problemas con la configuración regional española, donde iOS utiliza coma
como separador decimal.

Añade **Solicitar entrada**:

-   Tipo: Texto
-   Pregunta: `💰 Importe`

Guarda el resultado como `Importe introducido`.

Después añade **Reemplazar texto**:

``` text
Buscar: ,
Reemplazar por: .
En: [Importe introducido]
```

Guarda el resultado como `Importe API`.

Ejemplo:

``` text
18,58
↓
Reemplazar "," por "."
↓
18.58
```

------------------------------------------------------------------------

## 4. Solicitar concepto

Añade **Solicitar entrada**:

-   Tipo: Texto
-   Pregunta: `📝 Concepto`

Guarda el resultado como `Concepto`.

------------------------------------------------------------------------

## 5. Seleccionar categoría

Añade **Obtener valor de diccionario**:

``` text
Clave: categories
Diccionario: Opciones Splitly
```

Después añade **Elegir de lista** usando como entrada el **Valor del
diccionario** de la acción anterior.

Pregunta: `📂 Categoría`

Guarda el elemento seleccionado como `Categoría seleccionada`.

------------------------------------------------------------------------

## 6. Seleccionar grupo

Añade **Obtener valor de diccionario**:

``` text
Clave: groups
Diccionario: Opciones Splitly
```

Después añade **Elegir de lista** usando el valor obtenido.

Pregunta: `🏠 Grupo`

Guarda el resultado como `Grupo seleccionado`.

------------------------------------------------------------------------

## 7. Cargar los participantes del grupo

Después de seleccionar el grupo, consulta:

``` http
GET https://TU_DOMINIO/api/shortcuts/options.php?group=[Grupo seleccionado]
Authorization: Bearer [Token Splitly]
```

`Grupo seleccionado` debe insertarse como variable mágica.

Guarda la respuesta como `Opciones Grupo`.

Ejemplo de respuesta:

``` json
{
  "ok": true,
  "groups": ["Piso compartido", "Mascotas"],
  "categories": ["Alimentación", "Mascotas", "Transporte"],
  "payers": ["Joshue Freire", "Oreana Lopez"],
  "selected_group": "Mascotas"
}
```

------------------------------------------------------------------------

## 8. Seleccionar quién pagó

Añade **Obtener valor de diccionario**:

``` text
Clave: payers
Diccionario: Opciones Grupo
```

Después añade **Elegir de lista** usando el valor obtenido.

Pregunta: `💳 ¿Quién pagó?`

Guarda el resultado como `Pagador`.

------------------------------------------------------------------------

## 9. Indicar si el gasto se divide

Añade **Elegir de menú**.

Pregunta:

``` text
👥 ¿Dividir gasto?
```

Opciones: **Sí** / **No**.

Si selecciona **Sí**:

``` text
Número: 1
Definir variable Compartido = Número
```

Si selecciona **No**:

``` text
Número: 0
Definir variable Compartido = Número
```

Resultado:

``` text
Sí → Compartido = 1
No → Compartido = 0
```

------------------------------------------------------------------------

## 10. Construir el gasto

Después de `Terminar menú`, crea un **Diccionario**:

  Clave           Valor
  --------------- --------------------------
  `amount`        `Importe API`
  `category`      `Categoría seleccionada`
  `description`   `Concepto`
  `group`         `Grupo seleccionado`
  `paid_by`       `Pagador`
  `is_shared`     `Compartido`
  `split_type`    `equal`

Las variables deben insertarse mediante **Variables mágicas**.

Ejemplo:

``` json
{
  "amount": "18.58",
  "category": "Mascotas",
  "description": "Comida para el perro",
  "group": "Mascotas",
  "paid_by": "Joshue Freire",
  "is_shared": 1,
  "split_type": "equal"
}
```

Guarda el diccionario como `Gasto`.

Cuando `is_shared` es `1`, Splitly reparte el gasto equitativamente
entre los miembros activos del grupo. Cuando es `0`, el gasto pertenece
únicamente al pagador y no genera deuda compartida.

------------------------------------------------------------------------

## 11. Enviar el gasto a Splitly

Añade **Obtener contenido de URL**:

``` text
https://TU_DOMINIO/api/shortcuts/expenses.php
```

Configura:

``` text
Método: POST
Cabecera:
Authorization: Bearer [Token Splitly]

Cuerpo de la solicitud: JSON
```

Campos JSON:

``` text
amount       → [Importe API]
category     → [Categoría seleccionada]
description  → [Concepto]
group        → [Grupo seleccionado]
paid_by      → [Pagador]
is_shared    → [Compartido]
split_type   → equal
```

Guarda la respuesta como `Respuesta Splitly`.

------------------------------------------------------------------------

## 12. Comprobar el resultado

Obtén `ok` de `Respuesta Splitly`.

Después utiliza una condición **Si**.

Si `ok` es verdadero:

1.  Obtén `message` de `Respuesta Splitly`.
2.  Muestra una notificación: `✅ [message]`.

Si no:

1.  Obtén `message` de `Respuesta Splitly`.
2.  Muestra una alerta: `❌ [message]`.

Ejemplo:

``` json
{
  "ok": true,
  "message": "Gasto registrado correctamente."
}
```

------------------------------------------------------------------------

## 13. Flujo completo

``` text
Obtener token.txt
        ↓
Leer token
        ↓
¿Existe?
 ├─ NO → Solicitar token → Guardarlo
 └─ SÍ → Utilizar token existente
        ↓
GET options.php
        ↓
Opciones Splitly
        ↓
💰 Importe (Texto)
        ↓
Cambiar "," → "."
        ↓
Importe API
        ↓
📝 Concepto
        ↓
Obtener categories
        ↓
📂 Elegir categoría
        ↓
Obtener groups
        ↓
🏠 Elegir grupo
        ↓
GET options.php?group=[Grupo seleccionado]
        ↓
Obtener payers
        ↓
💳 Elegir pagador
        ↓
👥 ¿Dividir gasto?
 ├─ Sí → Compartido = 1
 └─ No → Compartido = 0
        ↓
Crear Diccionario
        ↓
POST expenses.php
        ↓
Respuesta Splitly
        ↓
¿ok?
 ├─ Sí → ✅ Mostrar message
 └─ No → ❌ Mostrar message
```

------------------------------------------------------------------------

## 14. Seguridad del token

Actualmente el Atajo almacena el token en:

``` text
Shortcuts/Splitly/token.txt
```

Esto evita guardar la contraseña de Splitly y permite revocar
independientemente el acceso del Atajo.

El token:

-   Identifica al usuario.
-   No contiene la contraseña de Splitly.
-   Debe limitarse a los endpoints necesarios para Atajos.
-   Puede revocarse desde la configuración de Splitly.
-   No debe permitir consultar grupos pertenecientes a otros usuarios.

### Mejora futura

Para una distribución pública de Splitly, se recomienda sustituir
`token.txt` por almacenamiento seguro mediante **iOS Keychain**.

Una posible arquitectura futura:

``` text
Splitly iOS / App Intent
        ↓
Token almacenado en Keychain
        ↓
Atajo / Siri
        ↓
API Splitly
```

------------------------------------------------------------------------

## 15. Resultado

Una vez configurado, el uso diario queda reducido a:

``` text
Ejecutar Splitly
↓
💰 18,58
↓
📝 Comida para el perro
↓
📂 Mascotas
↓
🏠 Mascotas
↓
💳 Joshue Freire
↓
👥 Dividir: Sí
↓
✅ Gasto registrado
```

El token no vuelve a solicitarse mientras `token.txt` exista y sea
válido.
