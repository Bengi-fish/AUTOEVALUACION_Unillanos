# Datos

## `semillas/`: catálogos comunes

| Archivo | Origen | Notas |
|---|---|---|
| `lineamientos.csv` | Acuerdo CESU 02 de 2020, Acuerdo CESU 01 de 2025, Decreto 1330 de 2019 | `id` es la clave usada por los demás CSV. |
| `elementos_modelo.csv` | Informe IE 2018-2022 (índice) y Acuerdo CESU 01 de 2025 | CNA 2020: 12 factores + 48 características. CESU 2025: 12 + 51 + 70 aspectos. Decreto 1330: 6 condiciones institucionales + 9 de programa. `equivale_a_id` (CESU 2025 → CNA 2020, factores 1–11) es una **propuesta**: validar con Acreditación. |
| `grados_cumplimiento.csv` | Tabla 3.1 del informe | El informe pone Insatisfactorio en 2,6–3,0; aquí se dejó 2,6–2,9 para no chocar con Aceptable. |
| `estamentos.csv` | Metodología del informe | |
| `sedes.csv` | Informe (sedes San Antonio, Barcelona y Emporio) | Falta el municipio de cada sede. |
| `facultades.csv`, `programas.csv`, `proyectos_institucionales.csv` | Hoja oculta "Listas" del Excel FO-GCL-20 | Faltan SNIES, modalidad, sede y resoluciones de cada programa. |

## `importacion/<programa>/`: datos de un programa

| Archivo | Origen |
|---|---|
| `proceso.csv`, `valoraciones_factores.csv` | Informe de autoevaluación (Tabla 5.1) |
| `plan.csv`, `metas.csv`, `hallazgos_plan.csv`, `indicadores.csv`, `programacion_anual.csv`, `seguimientos.csv`, `responsables.csv` | Excel FO-GCL-20, generados con `herramientas/plan_excel_a_csv.py` |
| `equivalencias_factores_plan.csv` | Manual: factor del Excel → elemento del modelo |
| `hallazgos_informe.csv` | (Fase 5.2 de la guía) fortalezas y aspectos por mejorar del informe |

Valores en formato numérico con punto decimal (`0.6` = 60 %). Las claves (`M01`, `M01-I1`, `H01`, `CNA20-F02`) son estables y las usa Migrate para relacionar registros.
