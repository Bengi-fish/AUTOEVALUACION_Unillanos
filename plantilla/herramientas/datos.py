# -*- coding: utf-8 -*-
"""Datos de ejemplo del prototipo UNILLANOS (contenido ilustrativo, no oficial).
En Drupal llegan desde Views / JSON:API; aquí se generan para poder maquetar."""
import json, re

ORIG_JS = "/tmp/claude-0/-home-claude/fc792407-74ec-58cd-ae72-9308f7145c2f/scratchpad/app_v1.js"
_js = open(ORIG_JS, encoding="utf-8").read()
_orig = json.loads(re.search(r"var DATA = (\{.*?\});\n", _js, flags=re.S).group(1))
ICONS = json.loads(re.search(r"var ICONS = (\{.*?\});\n", _js, flags=re.S).group(1))

# Iconos nuevos para tarjetas y secciones
ICONS.update({
    "building": '<path d="M3 21h18M5 21V10M9 21V10M15 21V10M19 21V10M2 10l10-6 10 6z"/>',
    "cap": '<path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11.5V16c0 1.5 3 3 6 3s6-1.5 6-3v-4.5M22 9v6"/>',
    "clipboard": '<rect x="6" y="4" width="12" height="17" rx="2"/><path d="M9 4h6v3H9zM9 12h6M9 16h4"/>',
    "mic": '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/>',
    "book": '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 21V5"/>',
    "scale": '<path d="M12 3v18M6 21h12M5 7h14"/><path d="m5 7-3 7a3 3 0 0 0 6 0zM19 7l-3 7a3 3 0 0 0 6 0z"/>',
    "award": '<circle cx="12" cy="9" r="6"/><path d="m8.5 14 -1.5 7 5-3 5 3-1.5-7"/>',
    "target": '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
    "layers": '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
})

# --------------------------------------------------------------------------
# Institucional: sedes (los valores por sede son de ejemplo)
# --------------------------------------------------------------------------
SEDES = {
    "villavicencio": {
        "nombre": "Villavicencio",
        "stats": {"factores": "12", "participantes": "3.126", "acciones": "22", "global": "4,2"},
        "delta": {},
    },
    "granada": {
        "nombre": "Granada",
        "stats": {"factores": "12", "participantes": "1.686", "acciones": "15", "global": "4,2"},
        # La suma de variaciones es cero para que el promedio sea igual.
        "delta": {"f1": -0.1, "f2": 0.1, "f3": -0.2, "f4": 0.0, "f5": 0.1, "f6": 0.2,
                  "f7": -0.1, "f8": 0.1, "f9": -0.3, "f10": 0.1, "f11": 0.0, "f12": 0.1},
    },
}

# --------------------------------------------------------------------------
# Programa de ejemplo: Ingeniería Electrónica (los 12 factores y sus nombres
# siguen el informe de autoevaluación 2018-2022; los valores son inventados)
# --------------------------------------------------------------------------
def fac(n, name, score, desc, strengths, plan):
    return {"key": "p%d" % n, "n": n, "name": name, "score": score, "desc": desc,
            "strengths": strengths, "plan": plan, "label": "Factor %02d" % n, "tag": "F%02d" % n}

RESP_DIR = "Dirección del programa"
NAC = [
 fac(1, "Proyecto educativo del programa e identidad institucional", 4.3,
  "El programa cuenta con un *proyecto educativo* coherente con la *misión* institucional, con *objetivos de formación* y *perfil de egreso* definidos y revisados de forma *periódica* con participación de profesores, estudiantes y egresados.",
  ["Proyecto educativo del programa actualizado y socializado", "Perfil de egreso articulado con las necesidades del sector productivo regional", "Revisión curricular periódica con participación de egresados", "Pertinencia social reconocida por empleadores de la región"],
  [["Baja apropiación del proyecto educativo entre estudiantes de primeros semestres.", "Incluir una sesión de socialización del proyecto educativo en la inducción del programa.", [RESP_DIR, "Comité de programa"], "corto"],
   ["El perfil de egreso no se contrasta de forma sistemática con el mercado laboral.", "Realizar un estudio de pertinencia cada dos años con empleadores y egresados.", [RESP_DIR, "Oficina de egresados"], "mediano"]]),
 fac(2, "Estudiantes", 4.1,
  "Los estudiantes cuentan con *mecanismos de ingreso* claros, *reglamento estudiantil* conocido y *apoyos académicos* que favorecen su *formación integral* y su *capacidad de trabajo autónomo*.",
  ["Reglamento estudiantil conocido y aplicado", "Monitorías y tutorías en asignaturas de alta dificultad", "Participación en semilleros y actividades de formación integral", "Estímulos académicos para estudiantes destacados"],
  [["Baja participación en actividades de formación integral.", "Programar actividades extracurriculares dentro del horario académico.", ["Bienestar institucional", RESP_DIR], "corto"],
   ["Estímulos económicos insuficientes para estudiantes con alto desempeño.", "Gestionar nuevas modalidades de apoyo y reconocimiento académico.", ["Vicerrectoría Académica"], "largo"]]),
 fac(3, "Profesores", 4.2,
  "El programa cuenta con un *núcleo de profesores* con *formación avanzada* y *dedicación* adecuada, vinculados mediante *procesos de selección* transparentes y con oportunidades de *desarrollo profesoral*.",
  ["Alto porcentaje de profesores con maestría o doctorado", "Plan de formación y desarrollo profesoral vigente", "Evaluación periódica del desempeño docente", "Estatuto profesoral conocido por la comunidad"],
  [["Proporción de profesores de tiempo completo inferior a la meta del programa.", "Gestionar la vinculación de nuevos profesores de tiempo completo en el área de profundización.", ["Vicerrectoría Académica", "Facultad de Ciencias Básicas e Ingeniería"], "largo"],
   ["Baja participación de profesores en formación en segunda lengua y estancias.", "Establecer un plan anual de formación en segunda lengua y movilidad.", [RESP_DIR, "Oficina de relaciones internacionales"], "mediano"]]),
 fac(4, "Egresados", 3.9,
  "El programa realiza *seguimiento* a sus *egresados* y reconoce su *impacto* en el medio *social* y *académico*, aunque requiere fortalecer los *mecanismos de contacto* y la medición de su trayectoria.",
  ["Base de datos de egresados activa", "Participación de egresados en el comité de programa", "Egresados vinculados a empresas del sector tecnológico", "Encuentros anuales de egresados"],
  [["Base de datos de egresados con información desactualizada.", "Implementar una encuesta anual de actualización de datos y trayectoria laboral.", ["Oficina de egresados", RESP_DIR], "corto"],
   ["No se mide el impacto de los egresados de forma sistemática.", "Diseñar un estudio de impacto de egresados con periodicidad cuatrienal.", ["Oficina de egresados", "Oficina Asesora de Planeación"], "mediano"]]),
 fac(5, "Aspectos académicos y resultados de aprendizaje", 4.2,
  "El *plan de estudios* es *integral* y *flexible*, con *estrategias pedagógicas* y un *sistema de evaluación* orientados al logro de los *resultados de aprendizaje* y las *competencias* definidas.",
  ["Resultados de aprendizaje definidos por asignatura", "Laboratorios y proyectos integradores en el ciclo de formación", "Sistema de evaluación de estudiantes conocido y aplicado", "Estrategias pedagógicas con apoyo de plataformas virtuales"],
  [["Los resultados de aprendizaje no se evalúan de forma sistemática al finalizar cada ciclo.", "Implementar pruebas de seguimiento a resultados de aprendizaje por ciclo.", [RESP_DIR, "Comité curricular"], "mediano"],
   ["Baja flexibilidad curricular en las electivas de profundización.", "Ampliar la oferta de electivas y reconocer créditos de movilidad.", ["Comité curricular"], "largo"]]),
 fac(6, "Permanencia y graduación", 3.8,
  "La Universidad y el programa disponen de *estrategias* para la *permanencia* y la *graduación*, con *caracterización* de estudiantes y *alertas tempranas* que aún requieren consolidarse.",
  ["Caracterización de estudiantes en cada cohorte", "Alertas tempranas de seguimiento académico", "Tutorías para estudiantes en riesgo de deserción", "Mecanismos de selección y admisión definidos"],
  [["Tasa de deserción en primeros semestres superior a la meta.", "Reforzar el acompañamiento académico y psicosocial en primer y segundo semestre.", ["Bienestar institucional", RESP_DIR], "corto"],
   ["Tiempo promedio de graduación mayor al previsto en el plan de estudios.", "Diseñar rutas de trabajo de grado con seguimiento semestral.", [RESP_DIR, "Comité de trabajo de grado"], "mediano"]]),
 fac(7, "Interacción con el entorno nacional e internacional", 3.7,
  "El programa *interactúa* con su *entorno* mediante convenios, redes académicas y *movilidad* de profesores y estudiantes, con avances en *segunda lengua* que están por consolidar.",
  ["Convenios vigentes con empresas y universidades", "Participación en redes académicas del área", "Proyectos con el sector productivo de la región", "Visitas y pasantías en el ámbito nacional"],
  [["Movilidad estudiantil saliente baja frente a la matrícula.", "Crear una convocatoria anual de movilidad con apoyos económicos.", ["Oficina de relaciones internacionales"], "mediano"],
   ["Bajo nivel de segunda lengua en estudiantes de último año.", "Incorporar cursos de segunda lengua articulados con el plan de estudios.", ["Instituto de idiomas", RESP_DIR], "largo"]]),
 fac(8, "Aportes de la investigación, la innovación, el desarrollo tecnológico y la creación", 4.0,
  "El programa fomenta la *formación para la investigación* mediante *semilleros*, *grupos de investigación* y proyectos que generan *producción académica* y *desarrollo tecnológico*.",
  ["Grupos de investigación categorizados en el área", "Semilleros de investigación con participación estudiantil", "Productos de investigación con profesores y estudiantes", "Proyectos de desarrollo tecnológico con aliados externos"],
  [["Producción científica concentrada en pocos profesores.", "Establecer metas de producción y acompañamiento para nuevos investigadores.", ["Centro de investigaciones", RESP_DIR], "mediano"],
   ["Pocos proyectos de innovación con financiación externa.", "Gestionar convocatorias de financiación con aliados del sector productivo.", ["Dirección de investigaciones"], "largo"]]),
 fac(9, "Bienestar de la comunidad académica del programa", 4.4,
  "La comunidad del programa accede a *programas y servicios* de *bienestar* con *participación* y *seguimiento*, que favorecen el *desarrollo integral* y la *permanencia*.",
  ["Oferta de servicios de salud, cultura y deporte", "Participación de estudiantes y profesores en actividades de bienestar", "Seguimiento periódico a la percepción de bienestar", "Apoyos socioeconómicos para estudiantes"],
  [["Baja difusión de los servicios de bienestar entre los estudiantes del programa.", "Fortalecer la comunicación de los servicios mediante los canales del programa.", ["Bienestar institucional"], "corto"],
   ["Participación de profesores en actividades de bienestar menor a la esperada.", "Programar actividades en horarios compatibles con la carga académica.", ["Bienestar institucional", RESP_DIR], "mediano"]]),
 fac(10, "Medios educativos y ambientes de aprendizaje", 4.1,
  "El programa dispone de *recursos bibliográficos*, *laboratorios* y *plataformas* de apoyo a la docencia y al aprendizaje, y desarrolla *estrategias* de capacitación para su uso.",
  ["Laboratorios de electrónica y telecomunicaciones dotados", "Acceso a bases de datos y recursos bibliográficos especializados", "Plataforma virtual de apoyo a las asignaturas", "Capacitación periódica a profesores en recursos educativos"],
  [["Equipos de laboratorio con obsolescencia parcial.", "Formular un plan de renovación escalonada de equipos de laboratorio.", [RESP_DIR, "Vicerrectoría de Recursos Universitarios"], "largo"],
   ["Bajo uso de bases de datos especializadas por parte de los estudiantes.", "Realizar capacitaciones semestrales en el uso de recursos bibliográficos.", ["Biblioteca", RESP_DIR], "corto"]]),
 fac(11, "Organización, administración y financiación del programa", 4.0,
  "El programa cuenta con una *organización administrativa* clara, *dirección y gestión* con *sistemas de información* adecuados y *financiación* orientada al *aseguramiento de la alta calidad*.",
  ["Estructura administrativa del programa definida y conocida", "Sistemas de información institucionales disponibles para la gestión", "Procesos de aseguramiento de la calidad en funcionamiento", "Comunicación periódica con la comunidad del programa"],
  [["La información del programa está dispersa en varios sistemas.", "Consolidar un tablero de indicadores del programa con datos actualizados.", ["Oficina Asesora de Planeación", RESP_DIR], "mediano"],
   ["El presupuesto del programa no tiene proyección plurianual.", "Formular un plan de inversión a cuatro años alineado con el plan de mejoramiento.", [RESP_DIR, "Oficina Asesora de Planeación"], "largo"]]),
 fac(12, "Recursos físicos y tecnológicos", 3.9,
  "La *infraestructura física* y los *recursos informáticos y de comunicación* son adecuados para el desarrollo del programa, con necesidades de *actualización* y *ampliación* de espacios.",
  ["Aulas y laboratorios en buen estado de conservación", "Conectividad y servicios informáticos para estudiantes y profesores", "Espacios de trabajo para proyectos y semilleros", "Plan de mantenimiento de infraestructura vigente"],
  [["Capacidad de los laboratorios insuficiente en horarios de alta demanda.", "Ampliar los turnos de laboratorio y evaluar un nuevo espacio de práctica.", [RESP_DIR, "Vicerrectoría de Recursos Universitarios"], "mediano"],
   ["Cobertura de conectividad inalámbrica limitada en algunos espacios.", "Ampliar la cobertura inalámbrica en aulas y laboratorios.", ["Oficina de Sistemas y TIC"], "corto"]]),
]

def dim(n, name, score, desc, strengths, plan):
    return {"key": "i%d" % n, "n": "D%d" % n, "name": name, "score": score, "desc": desc,
            "strengths": strengths, "plan": plan, "label": "Dimensión %d" % n, "tag": "D%d" % n}

INTL = [
 dim(1, "Contexto institucional", 4.2,
  "El programa se inscribe en una *institución* con *misión*, *gobierno* y *políticas de calidad* claras, y un *marco normativo* que respalda su *autonomía académica* y su *proyección regional*.",
  ["Misión y proyecto institucional conocidos por la comunidad", "Políticas de aseguramiento de la calidad vigentes", "Gobierno universitario con participación de los estamentos", "Presencia regional reconocida en la Orinoquia"],
  [["La política de internacionalización no se refleja en metas del programa.", "Incluir metas de internacionalización en el plan de acción anual del programa.", [RESP_DIR, "Oficina de relaciones internacionales"], "mediano"],
   ["Información institucional disponible solo en español.", "Publicar un resumen del programa y de la Universidad en un segundo idioma.", ["Oficina de Comunicaciones"], "corto"]]),
 dim(2, "Proyecto académico", 4.0,
  "El *proyecto académico* define *perfil de egreso*, *plan de estudios* y *resultados de aprendizaje* comparables con *referentes internacionales*, y se evalúa de manera *sistemática*.",
  ["Perfil de egreso con competencias comparables a referentes internacionales", "Plan de estudios con resultados de aprendizaje definidos", "Investigación y extensión articuladas con la formación", "Mecanismos de evaluación y revisión curricular"],
  [["Pocos referentes internacionales documentados en el diseño curricular.", "Elaborar un estudio comparado del plan de estudios con programas de referencia.", ["Comité curricular", RESP_DIR], "mediano"],
   ["Baja oferta de asignaturas en segunda lengua.", "Ofrecer asignaturas electivas dictadas en inglés.", [RESP_DIR, "Instituto de idiomas"], "largo"]]),
 dim(3, "Comunidad universitaria", 4.1,
  "La *comunidad universitaria* (estudiantes, profesores, egresados y personal de apoyo) cuenta con *condiciones*, *servicios* y *oportunidades de movilidad* que favorecen el *desarrollo* del programa.",
  ["Profesores con formación avanzada y producción académica", "Seguimiento a estudiantes y egresados", "Servicios de apoyo para el personal académico", "Participación en redes académicas internacionales"],
  [["Movilidad entrante de estudiantes y profesores muy baja.", "Establecer convenios para recibir visitantes académicos cada semestre.", ["Oficina de relaciones internacionales"], "mediano"],
   ["Seguimiento a egresados en el exterior inexistente.", "Incluir en la encuesta de egresados preguntas sobre trayectoria internacional.", ["Oficina de egresados"], "corto"]]),
 dim(4, "Infraestructura", 3.9,
  "La *infraestructura* de *aulas*, *laboratorios*, *bibliotecas* y *recursos tecnológicos* es adecuada y se *mantiene* y *actualiza* con base en las necesidades del programa.",
  ["Laboratorios con equipos para las prácticas del plan de estudios", "Biblioteca con recursos físicos y digitales", "Conectividad y servicios informáticos", "Condiciones de accesibilidad en los edificios del programa"],
  [["Algunos laboratorios no cumplen los estándares de seguridad internacionales.", "Realizar un diagnóstico de seguridad y priorizar las adecuaciones.", [RESP_DIR, "Vicerrectoría de Recursos Universitarios"], "mediano"],
   ["Equipos con licencias vencidas en algunas aulas de cómputo.", "Renovar las licencias de software de ingeniería.", ["Oficina de Sistemas y TIC"], "corto"]]),
]

# Listado de programas (ejemplo) para el buscador
PROGRAMAS = [
 {"id": "ing-electronica", "nombre": "Ingeniería Electrónica", "facultad": "Ciencias Básicas e Ingeniería", "sede": "Villavicencio", "nivel": "Pregrado", "ejemplo": True},
 {"id": "ing-sistemas", "nombre": "Ingeniería de Sistemas", "facultad": "Ciencias Básicas e Ingeniería", "sede": "Villavicencio", "nivel": "Pregrado"},
 {"id": "biologia", "nombre": "Biología", "facultad": "Ciencias Básicas e Ingeniería", "sede": "Villavicencio", "nivel": "Pregrado"},
 {"id": "ing-agroindustrial", "nombre": "Ingeniería Agroindustrial", "facultad": "Ciencias Agropecuarias y Recursos Naturales", "sede": "Villavicencio", "nivel": "Pregrado"},
 {"id": "mvz", "nombre": "Medicina Veterinaria y Zootecnia", "facultad": "Ciencias Agropecuarias y Recursos Naturales", "sede": "Villavicencio", "nivel": "Pregrado"},
 {"id": "enfermeria", "nombre": "Enfermería", "facultad": "Ciencias de la Salud", "sede": "Villavicencio", "nivel": "Pregrado"},
 {"id": "contaduria", "nombre": "Contaduría Pública", "facultad": "Ciencias Económicas", "sede": "Villavicencio", "nivel": "Pregrado"},
 {"id": "admin-granada", "nombre": "Administración de Empresas", "facultad": "Ciencias Económicas", "sede": "Granada", "nivel": "Pregrado"},
 {"id": "lic-fisica", "nombre": "Licenciatura en Educación Física y Deporte", "facultad": "Ciencias Humanas y de la Educación", "sede": "Villavicencio", "nivel": "Pregrado"},
 {"id": "esp-gerencia", "nombre": "Especialización en Gerencia de Proyectos", "facultad": "Ciencias Económicas", "sede": "Villavicencio", "nivel": "Posgrado"},
]

def build():
    inst = {"factores": [dict(f, label="Factor %02d" % f["n"], tag="F%02d" % f["n"]) for f in _orig["factores"]],
            "adendas": [dict(a, label="Adenda %s" % a["n"][1:], tag=a["n"]) for a in _orig["adendas"]]}
    return {
        "institucional": inst,
        "sedes": SEDES,
        "programa": {"nombre": "Ingeniería Electrónica", "nacional": NAC, "internacional": INTL},
        "programas": PROGRAMAS,
    }

if __name__ == "__main__":
    d = build()
    print({k: (len(v) if hasattr(v, "__len__") else v) for k, v in d.items()})
    print(sum(len(f["plan"]) for f in NAC), sum(len(f["plan"]) for f in INTL))
