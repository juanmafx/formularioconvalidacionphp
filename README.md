
<p align="center"><strong>Formulario PHP con Validación y Envío a Página Objetivo</strong></p>

<p align="center">
Un formulario simple en PHP con validación de campos y envío de datos.
</p>

<p align="center">
<img src="https://img.shields.io/badge/PHP-5.6%2B-blue.svg" alt="PHP Version">
<img src="https://img.shields.io/badge/Estado-Activo-brightgreen.svg" alt="Estado del Proyecto">
<img src="https://img.shields.io/badge/Licencia-Libre-lightgrey.svg" alt="Licencia">
</p>

## Acerca del Formulario

Este proyecto proporciona un formulario que valida datos esenciales antes de su envío. Es ideal como base para proyectos que requieran captura de información del usuario.

El formulario valida los siguientes campos obligatorios:

- **Nombre**
- **Email**
- **Teléfono**

Gracias a HTML5, el campo **Email** aplica una validación automática de formato, mientras que **Nombre** y **Teléfono** son validados manualmente mediante PHP.

## Funcionamiento

Este formulario funciona con dos archivos principales:

- `class.phpmailer.php`
- `index.php`

En `index.php`, antes de la etiqueta `<html>`, se incluye el código PHP necesario para procesar la validación y el envío de los datos.

El formulario HTML contiene los `input` para capturar los datos requeridos.

## Requisitos

- Servidor web con PHP 5.6 o superior
- Navegador web moderno con soporte para HTML5

## Instalación

1. Clonar o descargar los archivos del proyecto.
2. Asegurarse de tener habilitada la función de envío de correos en el servidor.
3. Configurar `class.phpmailer.php` si se requiere personalización en el envío de correos.
4. Ejecutar `index.php` en un entorno de servidor local o remoto.

## Palabras clave

- Formulario PHP
- Validación de formulario
- Formulario de contacto
- Formulario de contacto con validación
- Envío de correos en PHP
- PHP Mailer
- Validación HTML5

## Contribuciones

Gracias por considerar contribuir al proyecto. ¡Cualquier mejora, comentario o sugerencia es bienvenida!

## Código de Conducta

Para mantener una comunidad abierta y respetuosa, por favor revisa y respeta las buenas prácticas de contribución.

## Vulnerabilidades de Seguridad

Si descubres alguna vulnerabilidad de seguridad en este formulario, por favor contáctame para poder solucionarlo de inmediato.

## Licencia

Este proyecto es software libre y está disponible bajo una licencia abierta. Puedes usarlo, modificarlo y adaptarlo libremente.

---

<p align="center">Hecho con 💻 por Juanmafx</p>
