# Pendientes con Acreditación

Resultado de una revisión de solo lectura del repositorio. Recoge de dónde salen las equivalencias de factores del plan de mejoramiento y la regla de destino de los hallazgos, y qué falta confirmar.

## 1. Fuentes de factores y elementos

Los 210 elementos están en `datos/semillas/elementos_modelo.csv`:

| Lineamiento | Cantidad |
|---|---|
| CNA 2020 | 12 factores + 48 características |
| CESU 2025 | 12 factores + 51 características + 70 aspectos |
| Decreto 1330 | 17 condiciones |

Los lineamientos están en `datos/semillas/lineamientos.csv` (Acuerdo CESU 02 de 2020, Acuerdo CESU 01 de 2025, Decreto 1330 de 2019).

Según `datos/LEEME.md`, `elementos_modelo.csv` viene del índice del informe IE 2018-2022 y del Acuerdo CESU 01 de 2025. Los nombres del CNA 2020 se contrastaron después con el Acuerdo 02 de 2020 (`docs/fuentes/acuerdo-cesu-002-2020.pdf`, capítulo de programas): 12 factores y 48 características coinciden; solo se corrigió C45 a "Financiación del programa académico".

No hay equivalencias entre modelos: se retiró `equivale_a_id` / `field_equivale_a` de `elemento_modelo`. Las `equivalencias_factores_plan.csv` son otra cosa: traducen textos de numeración 2013 del Excel del plan a códigos CNA 2020.

Documentos en el repositorio:

- `docs/fuentes/informe-autoevaluacion-2022-ingenieria-electronica.pdf`: informe de autoevaluación. Origen de `valoraciones_factores.csv` y `proceso.csv` (Tabla 5.1).
- `docs/fuentes/plan-mejoramiento-ingenieria-electronica-2024-2-2030.xlsx`: plan de mejoramiento. Origen de `metas.csv`, `hallazgos_plan.csv` y los demás CSV del plan.
- `docs/fuentes/acuerdo-cesu-001-2025.docx`: Acuerdo CESU 01 de 2025.

No están cargados: el Decreto 1330 y el modelo CNA 2013. El Acuerdo CESU 02 de 2020 sí está (`acuerdo-cesu-002-2020.pdf`).

## 2. Cómo se hizo la conversión de factores

- Archivo: `datos/importacion/ingenieria-electronica/equivalencias_factores_plan.csv` (9 filas, manual, creado en el commit `3dc8377`).
- El emparejamiento es por texto completo y exacto, no por número ni por prefijo. Lo hace el plugin `unillanos_equivalencia` (`unillanos_migrate/.../Equivalencia.php`) desde `migrations/hallazgos_plan.yml`.
- Si el destino está vacío, el resultado es NULL (caso "Todos los factores").
- Ninguna fila cita un documento oficial. Las notas del CSV dicen que el plan usa la numeración 2013, pero ese modelo no está en el repositorio.

Casos dudosos (línea del CSV):

- Línea 7: "Factor 9 - Impacto de los egresados en el medio" → `CNA20-F04` ("equivale al factor 4 de 2020").
- Línea 8: "Factor 9. Bienestar de la Comunidad Académica del Programa" → `CNA20-F09`.
- Línea 4: "Factor 5 - Visibilidad nacional e internacional" → `CNA20-F07`.
- Línea 9: "Factor 10 - Recursos físicos y financieros" → `CNA20-F12`. En 2020 el F12 es "Recursos físicos y tecnológicos" y la financiación está en el F11.
- Línea 10: "Todos los factores" → sin elemento (meta transversal).

## 3. Tabla de verificación

| Texto del plan | Código asignado | Certeza |
|---|---|---|
| Factor 9. Bienestar de la Comunidad Académica del Programa | CNA20-F09 | Deducido por el nombre |
| Factor 9 - Impacto de los egresados en el medio | CNA20-F04 | Dudoso: depende de la numeración 2013, no cargada |
| Factor 5 - Visibilidad nacional e internacional | CNA20-F07 | Deducido por el nombre |
| Factor 10 - Recursos físicos y financieros | CNA20-F12 | Dudoso: el nombre mezcla F12 y F11 de 2020 |
| Todos los factores | (vacío) | Decisión de diseño, sin documento |

Se reimportarían las metas M14, M15 y M16 y los hallazgos H13 y H14 si alguna equivalencia cambia.

## 4. Origen de la regla de destino y del redondeo

No hay fuente documental. Es una decisión de diseño, marcada como "pendiente de confirmar con Acreditación".

- Regla de destino (< 4 `plan_mejoramiento`, = 4 `plan_accion`, > 4 `ninguno`): `docs/drupal/modelo-de-contenido.md:126`, `docs/mer/MER.md:274`, `CLAUDE.md:341`, `docs/IMPLEMENTACION_DRUPAL.md:32-33`. Código: `unillanos_autoeval/src/Service/SugerenciaDestino.php:55`.
- Redondeo a un decimal: `docs/drupal/modelo-de-contenido.md:123` y `docs/IMPLEMENTACION_DRUPAL.md:31`. Código: `CalculadorGrado.php:50-53`. Resuelve los huecos de la Tabla 3.1 (4,7–4,8 y 3,9–4,0).
- `modelo-de-contenido.md:126` dice "Regla del informe", pero no se pudo comprobar en el PDF.
- El redondeo se aplica al grado, no al destino: `SugerenciaDestino` compara el valor exacto (3,96 va a `plan_mejoramiento`; 4,27 va a `ninguno`).
- `plan_excel_a_csv.py:170` fija `destino = plan_mejoramiento` para las oportunidades del plan, por la naturaleza del archivo, no por la regla del 4.

## 5. Punto para Acreditación

**Con quién:** Acreditación institucional / oficina de autoevaluación. Los archivos no nombran una dependencia ni una persona, así que hay que confirmarla.

**Qué tener a mano:** el modelo CNA 2013 y el Acuerdo CESU 02 de 2020 (no están en el repositorio), el informe `docs/fuentes/informe-autoevaluacion-2022-ingenieria-electronica.pdf` y el plan `docs/fuentes/plan-mejoramiento-ingenieria-electronica-2024-2-2030.xlsx`.

**Preguntas:**

1. ¿El plan usa la numeración de factores de 2013? ¿"Factor 5 - Visibilidad" equivale al factor 7 de 2020?
2. ¿"Factor 9 - Impacto de los egresados" equivale al factor 4 de 2020?
3. ¿"Factor 10 - Recursos físicos y financieros" va al factor 12 (físicos y tecnológicos) o al 11 (financiación)?
4. ¿"Todos los factores" se deja como meta transversal, sin factor?
5. Un hallazgo valorado en menos de 4 va al plan de mejoramiento, igual a 4 al plan de acción y más de 4 a ninguno. ¿Es correcto?
6. ¿Un 4,27 cuenta como "igual a 4" o como "mayor que 4"? ¿Y un 3,96?
7. Para el grado (Pleno, Alto, etc.), ¿se redondea la nota a un decimal antes de compararla con la escala?

## Lo que no se pudo determinar

- Qué dice el informe en PDF (no hay lector de PDF en el entorno) ni el .docx del Acuerdo CESU 2025.
- Si la numeración 2013 que afirma el CSV es correcta.
- Quién decidió cada equivalencia (el repositorio no lo registra).
- Con qué dependencia o persona de Acreditación hablar.
