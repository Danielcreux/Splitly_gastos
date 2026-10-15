# Splitly

Plataforma para gestionar gastos compartidos entre parejas, amigos, hogares o equipos.

Splitly permite crear grupos, registrar gastos, repartir importes, consultar balances y visualizar la actividad financiera desde la web o desde la aplicación móvil Android.

<p align="center">

  <a href="https://splitly.freire-sanchez-valencia.es/">
    <img
      src="https://img.shields.io/badge/WEB-Ver%20aplicaci%C3%B3n-0B7A68?style=for-the-badge&logo=googlechrome&logoColor=white"
      alt="Ver aplicación web"
    />
  </a>

  <a href="https://github.com/Danielcreux/Splitly_gastos/releases/latest">
    <img
      src="https://img.shields.io/badge/ANDROID-Descargar%20APK-2E8B57?style=for-the-badge&logo=android&logoColor=white"
      alt="Descargar Android"
    />
  </a>

  <a href="https://www.icloud.com/shortcuts/4c7c20d7b672420a8e3f433cef0fd937">
    <img
      src="https://img.shields.io/badge/iOS-A%C3%B1adir%20Atajo-5B5BD6?style=for-the-badge&logo=apple&logoColor=white"
      alt="Añadir Atajo iOS"
    />
  </a>

</p>
  

---

## Disponible en

### Web

La versión web está disponible en:

**[splitly.freire-sanchez-valencia.es](https://splitly.freire-sanchez-valencia.es/)**

Diseñada para escritorio, tablet y móvil mediante una interfaz responsive.

### Android

La aplicación Android está disponible desde **GitHub Releases**:

**[Descargar última versión](https://github.com/Danielcreux/Splitly_gastos/releases/latest)**

Se distribuyen APK para distintas arquitecturas:

- **Universal** — recomendada si no conoces la arquitectura de tu dispositivo.
- **ARM64** — recomendada para la mayoría de móviles Android actuales.
- **ARM 32 bits** — para dispositivos antiguos.
- **x64** — principalmente para emuladores y dispositivos x86_64.

---

## Vista previa

![Splitly Web y Android](docs/screenshots/login.png)

---

## Qué permite hacer

- Crear y administrar grupos de gastos compartidos.
- Invitar usuarios y gestionar participantes.
- Registrar, editar y eliminar gastos.
- Indicar quién realizó cada pago.
- Dividir gastos entre los miembros de un grupo.
- Asignar gastos exclusivamente al pagador cuando sea necesario.
- Consultar cuánto debe o debe recibir cada usuario.
- Calcular balances automáticamente.
- Proponer liquidaciones entre participantes.
- Visualizar el historial de movimientos.
- Consultar estadísticas y evolución de gastos.
- Configurar presupuestos por grupo.
- Recibir y gestionar invitaciones y notificaciones.
- Mantener la sesión iniciada de forma segura.
- Registrar gastos desde Atajos de iOS y Siri mediante una API autenticada.

---

## Aplicación móvil

Splitly cuenta con una aplicación Android desarrollada con **.NET MAUI** que consume la misma API utilizada por la plataforma web.

La aplicación incluye:

- Dashboard con resumen financiero.
- Gestión de grupos.
- Registro y consulta de gastos.
- Saldos entre participantes.
- Estadísticas y gráficos.
- Actividad reciente.
- Navegación adaptada a dispositivos móviles.
- Tema claro y oscuro.
- Diseño basado en Material Design 3.

La aplicación móvil y la versión web comparten los mismos datos y usuarios.

---

## Plataforma web

La aplicación web proporciona una experiencia completa para gestionar los gastos desde escritorio o navegador móvil.

Entre sus principales módulos se encuentran:

- Resumen general.
- Grupos.
- Gastos.
- Saldos.
- Actividad.
- Estadísticas.
- Configuración.

El frontend consume endpoints JSON mediante `fetch`, mientras que las operaciones sensibles se validan nuevamente en el servidor.

---

## Tecnologías

| Área | Tecnología |
| --- | --- |
| Backend | PHP 8.1+ |
| API | REST / endpoints JSON |
| Base de datos | MySQL 8 |
| Acceso a datos | PDO |
| Web | HTML5, CSS3 y JavaScript Vanilla |
| Aplicación Android | .NET MAUI / C# |
| Gráficos | Chart.js |
| Servidor | Apache |
| Sistema | Linux / Ubuntu Server |
| Control de versiones | Git y GitHub |

---

## Arquitectura

```text
Splitly
│
├── Web
│   ├── PHP
│   ├── HTML5
│   ├── CSS3
│   └── JavaScript
│
├── API
│   ├── Autenticación
│   ├── Usuarios
│   ├── Grupos
│   ├── Gastos
│   ├── Balances
│   └── Atajos de iOS
│
├── Base de datos
│   └── MySQL
│
└── App móvil
    ├── .NET MAUI
    ├── C#
    ├── Android
    └── API compartida