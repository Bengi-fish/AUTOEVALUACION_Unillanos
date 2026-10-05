# Pendientes con Acreditación

Recoge de dónde salen las equivalencias de factores del plan de mejoramiento y la regla de destino de los hallazgos, y qué falta confirmar. Solo quedan abiertos la regla del 4 y el redondeo (sección 4).

## 1. Fuentes de factores y elementos

Los 210 elementos están en `datos/semillas/elementos_modelo.csv`:

| Lineamiento | Cantidad |
|---|---|
| CNA 2020 (Acuerdo CESU 02 de 2020) | 12 factores + 48 características |
| CESU 2025 (Acuerdo CESU 01 de 2025) | 12 factores + 51 características + 70 aspectos |
| Decreto 1330 | 17 condiciones |

Los lineamientos están en `datos/semillas/lineamientos.csv`.

**Decisión de diseño: el modelo se declara por proceso.** Cada proceso guarda en `field_lineamiento` el modelo con el que se hizo, y sus valoraciones, hallazgos y metas solo pueden usar elementos de ese modelo. Ingeniería Electrónica (PR-IE-2022) usa el Acuerdo CESU 02 de 2020; los procesos nuevos usarán el Acuerdo CESU 01 de 2025. **No hay equivalencias entre el modelo 2020 y el 2025**: los datos de cada proceso se quedan en su modelo (se retiró `equivale_a_id`).

Los nombres del CNA 2020 se contrastaron con el Acuerdo CESU 02 de 2020 (`docs/fuentes/acuerdo-cesu-002-2020.pdf`, capítulo de programas, págs. 24 a 33): 12 factores y 48 características coinciden. Solo se corrigió C45 a "Financiación del programa académico".

Documentos en el repositorio (`docs/fuentes/`):

- `informe-autoevaluacion-2022-ingenieria-electronica.pdf`: origen de `valoraciones_factores.csv` y `proceso.csv` (Tabla 5.1).
- `plan-mejoramiento-ingenieria-electronica-2024-2-2030.xlsx`: origen de `metas.csv`, `hallazgos_plan.csv` y los demás CSV del plan.
- `acuerdo-cesu-001-2025.docx` y `acuerdo-cesu-002-2020.pdf`.

No están cargados: el Decreto 1330 y el modelo CNA 2013.

## 2. Equivalencias de factores del plan: respaldadas por el informe

`datos/importacion/ingenieria-electronica/equivalencias_factores_plan.csv` (9 filas) traduce los textos de factor del Excel del plan, que usan la numeración CNA 2013, a códigos CNA 2020. **No es una equivalencia entre modelos del sistema**: solo sirve para importar el plan. El emparejamiento es por texto completo y exacto, lo hace el plugin `unillanos_equivalencia` desde `migrations/hallazgos_plan.yml`, y un destino vacío da NULL.

El informe lo confirma. La introducción (pág. 14 del PDF) dice que el proceso 2018–2022 se hizo con el Acuerdo CESU 02 de 2020 y los lineamientos 2013 se usaron en 2017. Las tablas "Balance Proceso Autoevaluación 2017 vs 2022" comparan los factores de una y otra numeración.

| Texto del plan | Código asignado | Certeza |
|---|---|---|
| Factor 9. Bienestar de la Comunidad Académica del Programa | CNA20-F09 | Mismo nombre y número en 2020 |
| Factor 9 - Impacto de los egresados en el medio | CNA20-F04 | Respaldado por el informe: Factor 9 de 2013 equivale al F04 (Balance, pág. 127) |
| Factor 5 - Visibilidad nacional e internacional | CNA20-F07 | Respaldado por el informe: Factor 5 de 2013 equivale al F07 (Balance, pág. 172) |
| Factor 10 - Recursos físicos y financieros | CNA20-F12 | Respaldado por el informe: lo físico va al F12 (pág. 225) y lo financiero al F11 (pág. 219). Las metas de laboratorios son de lo físico |
| Todos los factores | (vacío) | Decisión de diseño: meta transversal, sin elemento |

## 3. Cómo se hizo la conversión

- Archivo manual creado en el commit `3dc8377`; las notas se actualizaron con las páginas del informe.
- Si una equivalencia cambia, se reimportan las metas M14, M15 y M16 y los hallazgos H13 y H14.

## 4. Pendiente: regla del 4 y redondeo

Sin fuente documental. Es una decisión de diseño marcada como "pendiente de confirmar con Acreditación".

- Regla de destino (< 4 `plan_mejoramiento`, = 4 `plan_accion`, > 4 `ninguno`): `docs/drupal/modelo-de-contenido.md`, `docs/mer/MER.md`, `CLAUDE.md` y `docs/IMPLEMENTACION_DRUPAL.md`. Código: `unillanos_autoeval/src/Service/SugerenciaDestino.php`.
- Redondeo a un decimal: `docs/drupal/modelo-de-contenido.md` y `docs/IMPLEMENTACION_DRUPAL.md`. Código: `CalculadorGrado.php`. Resuelve los huecos de la Tabla 3.1 (4,7–4,8 y 3,9–4,0).
- El redondeo se aplica al grado, no al destino: `SugerenciaDestino` compara el valor exacto (3,96 va a `plan_mejoramiento`; 4,27 va a `ninguno`).
- `plan_excel_a_csv.py` fija `destino = plan_mejoramiento` para las oportunidades del plan, por la naturaleza del archivo, no por la regla del 4.
- `modelo-de-contenido.md` dice "Regla del informe", pero no se pudo comprobar en el PDF.

**Preguntas para Acreditación institucional / oficina de autoevaluación** (los archivos no nombran una dependencia ni una persona; hay que confirmarla):

1. Un hallazgo valorado en menos de 4 va al plan de mejoramiento, igual a 4 al plan de acción y más de 4 a ninguno. ¿Es correcto?
2. ¿Un 4,27 cuenta como "igual a 4" o como "mayor que 4"? ¿Y un 3,96?
3. Para el grado (Pleno, Alto, etc.), ¿se redondea la nota a un decimal antes de compararla con la escala?

## 5. Pendientes técnicos del modelo por proceso

No requieren a Acreditación. Quedan para fases posteriores.

- **PHPStan en scripts (baja prioridad):** `drupal/scripts/crear_modelo.php` tiene 2 errores previos de nivel 5, en las líneas ~554 y ~555 (`FieldStorageDefinitionInterface::save()` y `FieldConfigInterface::setLabel()`). Los scripts quedan fuera de la compuerta de PHPStan hasta corregirlos.
- **Webform de participación (Fase 8):** su elemento `elemento` no se filtra por el modelo del proceso.
- **Decreto 1330:** la restricción del modelo del proceso (`UnillanosModeloDelProceso`, servicio `ModeloProceso`) solo admite lineamientos de tipo `acreditacion`, así que excluye el Decreto 1330. Si se necesitan procesos de registro calificado, hay que ampliarla.
- **Catálogo CESU 2025:** comparar fila por fila `elementos_modelo.csv` (12 factores, 51 características y 70 aspectos) contra el Acuerdo CESU 01 de 2025 cuando se cree el primer proceso con ese modelo. El CNA 2020 ya se contrastó con el Acuerdo 02 de 2020.
- **Autor de los contenidos importados:** salen con autor "Anónimo (no verificado)". Decidir en la Fase 7 o la 9 si se les asigna un usuario.
- **Título de los procesos (Fase 7):** configurar el título automático. Los procesos deben tener un título corto y reconocible; el nombre largo actual ("Autoevaluación con fines de renovación de la acreditación en alta calidad 2018–2022") hace incómoda la búsqueda por autocompletado, que busca por título.
