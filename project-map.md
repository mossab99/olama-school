# Project Map: Olama School Weekly Plan (Eval 02)

## Directory Structure

- `/assets`
  - CSS, JS, and image assets. Handles the styling and interactivity for both the weekly plan views and the analysis dashboard.
- `/includes`
  - Core plugin logic and class files. Contains the main logic for managing weekly plans, curriculum coverage calculations, and database integrations.
- `/src`
  - Modern PHP source files (PSR-4) or compiled assets for the plugin's core modules.
- `/templates`
  - Frontend and admin display templates for the weekly plan views and reporting dashboards.
- `/vendor`
  - Third-party Composer dependencies for extended PHP functionality.

## Core Files

- `olama-school-weekly-plan.php`: Main plugin entry point and initialization logic.
- `ev-export-mapping.php`: Logic for mapping curriculum and exported material.

## Module Boundary

Olama School owns academic operations, curriculum, weekly plans, schedules,
teacher assignments, office hours, reports, and School settings. Exams,
evaluation, supervision, employees, KG, transportation, media, users, and
permissions are provided by their standalone Olama plugins. School requires
only Olama Core and Olama Users; feature plugins depend on School, never the
other way around.
