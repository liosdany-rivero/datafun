# jekyll

Se Utiliza para visualizar la documentacion en un servidor localmente y preparar las paginas estaticas en github pages

## Guía Completa desde Cero para Windows

Paso 1: Verificar Prerequisitos

1.1 Verificar si tienes Ruby instalado
Abre Símbolo del sistema (CMD) o PowerShell y ejecuta:

```cmd 
rem cmd

ruby --version
```

Si no tienes Ruby, descárgalo de: https://rubyinstaller.org/downloads/

- Descarga: Ruby+Devkit 3.2.2-1 (x64)

1.2 Instalar Ruby (si no lo tienes)

1. Ejecuta el instalador descargado
2. Marca "Add Ruby executables to your PATH"
3. Al finalizar, marca "Run 'ridk install'"
4. En la terminal que se abre, presiona Enter 3 veces
5. Espera a que termine la instalación
   
Paso 2: Instalar Bundler y Jekyll

2.1 Abrir CMD como Administrador

- Presiona Windows + R
- Escribe cmd
- Presiona Ctrl + Shift + Enter

2.2 Instalar Bundler

```cmd 
rem cmd
gem install bundler
```

2.3 Verificar instalación

```cmd 
rem cmd
ruby --version
gem --version
bundler --version
```` 

Paso 3: Crear la estructura del proyecto

3.1 Crear carpeta del proyecto

```cmd 
rem cmd
# Navegar a donde quieras crear el proyecto
cd Desktop

# Crear carpeta
mkdir datafun-erp
cd datafun-erp
````

3.2 Crear los archivos esenciales

Archivo 1: Gemfile (sin extensión)

``` ruby 
# ruby

source "https://rubygems.org"

gem "jekyll", "~> 4.4.1"
gem "just-the-docs"
gem "webrick"

# Plugins opcionales
gem "jekyll-sitemap"
gem "jekyll-seo-tag"

# Grupo de plugins de Jekyll
group :jekyll_plugins do
  gem "jekyll-sitemap"
  gem "jekyll-seo-tag"
end
```

Archivo 2: _config.yml

``` yml
# yml 

title: "DataFun - ERP"
description: "Sistema web de gestión ERP"
baseurl: "/datafun"
url: "http://localhost:4000"

# Tema para desarrollo local
theme: just-the-docs

# Configuración básica
lang: "es"
search_enabled: true

# Plugins
plugins:
  - jekyll-sitemap
  - jekyll-seo-tag

# Configuración de Just the Docs
aux_links:
  "Repositorio GitHub": "https://github.com/liosdany-rivero/datafun"

color_scheme: "dark"
heading_anchors: true

# Navegación
nav_external_links:
  - title: "GitHub Repo"
    url: "https://github.com/liosdany-rivero/datafun"

# Colecciones
collections:
  docs:
    permalink: "/:path/"
    output: true

# Configuración por defecto
defaults:
  - scope:
      path: ""
      type: docs
    values:
      layout: "default"
      search: true
      toc: true
```

Archivo 3: index.md

<pre>
---
layout: default
title: Inicio
nav_order: 1
---

# Bienvenido a DataFun ERP

Sistema web de gestión empresarial.

## Características principales

- Gestión de datos centralizada
- Reportes automáticos
- Dashboard interactivo
- Análisis en tiempo real

## Comenzar

Visita nuestra documentación para más detalles.
</pre>

Paso 5: Crear carpeta para páginas adicionales

```cmd
rem cmd

PS G:\XAMPP\htdocs\datafun\docs> mkdir _pages
PS G:\XAMPP\htdocs\datafun\docs> cd _pages
````

Paso 6: Crear página "Acerca de"

```cmd
rem cmd
PS G:\XAMPP\htdocs\datafun\docs\_pages> notepad acerca.md
````
Contenido:

<pre>
---
layout: default
title: "Acerca de"
nav_order: 2
parent: null
---

# Acerca de DataFun ERP

Sistema desarrollado para la gestión empresarial eficiente.

## Tecnologías utilizadas

- Jekyll
- Just the Docs
- Ruby
</pre>

Guardar y volver a la carpeta principal:

```cmd
rem cmd

PS G:\XAMPP\htdocs\datafun\docs\_pages> cd ..

````

Paso 7: Verificar estructura de archivos

```cmd
rem cmd

PS G:\XAMPP\htdocs\datafun\docs> dir

````

Deberías ver:

``` text

Mode                 LastWriteTime         Length Name
----                 -------------         ------ ----
d-----        10/12/2024   8:00 PM                _pages
-a----        10/12/2024   8:00 PM            1234 _config.yml
-a----        10/12/2024   8:00 PM             567 Gemfile
-a----        10/12/2024   8:00 PM             789 index.md

````

Paso 8: Instalar las dependencias

```cmd
rem cmd

PS G:\XAMPP\htdocs\datafun\docs> bundle install

````

Esto tomará unos minutos. Deberías ver algo como:

``` text

Fetching gem metadata from https://rubygems.org/.........
Resolving dependencies...
Using public_suffix 4.0.7
Using bundler 2.7.2
...
Bundle complete! 6 Gemfile dependencies, 30 gems now installed.

````

Paso 9: Verificar que Jekyll esté instalado correctamente

```cmd
rem cmd

PS G:\XAMPP\htdocs\datafun\docs> bundle exec jekyll --version

````

Debería mostrar: jekyll 4.4.1

Paso 11: Ejecutar el servidor de desarrollo

```cmd
rem cmd

PS G:\XAMPP\htdocs\datafun\docs> bundle exec jekyll serve --livereload --trace

````

Explicación de los flags:

--livereload: Recarga automáticamente cuando haces cambios

--trace: Muestra errores detallados si hay problemas

Lo que deberías ver cuando el servidor inicie:

``` text

Configuration file: G:/XAMPP/htdocs/datafun/docs/_config.yml
            Source: G:/XAMPP/htdocs/datafun/docs
       Destination: G:/XAMPP/htdocs/datafun/docs/_site
 Incremental build: disabled. Enable with --incremental
      Generating...
       Jekyll Feed: Generating feed for posts
                    done in 2.456 seconds.
 Auto-regeneration: enabled for 'G:/XAMPP/htdocs/datafun/docs'
    Server address: http://127.0.0.1:4000/datafun/
  Server running... press ctrl-c to stop.

  ```

Paso 12: Probar el sitio web
1. Abre tu navegador web (Chrome, Firefox, Edge)
2. Ve a esta URL exacta: http://localhost:4000/datafun/
3. O esta alternativa: http://127.0.0.1:4000/datafun/

Lo que deberías ver:
- Un sitio con fondo oscuro (tema dark de Just the Docs)
- Menú de navegación a la izquierda
- Título "DataFun - ERP"
- Tu contenido de index.md mostrado