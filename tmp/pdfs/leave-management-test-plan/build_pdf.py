from html import escape
from pathlib import Path

from docx import Document
from docx.table import Table as DocxTable
from docx.text.paragraph import Paragraph as DocxParagraph
from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import letter
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import inch
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import KeepTogether, Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle

ROOT = Path.cwd()
SOURCE = ROOT / "docs" / "Leave_Management_Senior_Developer_Test_Plan.docx"
OUTPUT = ROOT / "tmp" / "pdfs" / "leave-management-test-plan" / "Leave_Management_Senior_Developer_Test_Plan.pdf"

pdfmetrics.registerFont(TTFont("Arial", r"C:\Windows\Fonts\arial.ttf"))
pdfmetrics.registerFont(TTFont("Arial-Bold", r"C:\Windows\Fonts\arialbd.ttf"))
pdfmetrics.registerFont(TTFont("Arial-Italic", r"C:\Windows\Fonts\ariali.ttf"))
pdfmetrics.registerFont(TTFont("Arial-BoldItalic", r"C:\Windows\Fonts\arialbi.ttf"))
pdfmetrics.registerFontFamily("Arial", normal="Arial", bold="Arial-Bold", italic="Arial-Italic", boldItalic="Arial-BoldItalic")

BLUE = colors.HexColor("#17365D")
MID_BLUE = colors.HexColor("#D9EAF7")
LIGHT_BLUE = colors.HexColor("#F1F6FA")
GRID = colors.HexColor("#A9B8C6")
TEXT = colors.HexColor("#263746")
PAGE_WIDTH = letter[0] - 1.44 * inch

styles = getSampleStyleSheet()
styles.add(ParagraphStyle(name="DocTitle", fontName="Arial-Bold", fontSize=21, leading=25, textColor=BLUE, spaceAfter=6, keepWithNext=True))
styles.add(ParagraphStyle(name="Subtitle", fontName="Arial-Italic", fontSize=10, leading=14, textColor=colors.HexColor("#526577"), spaceAfter=12, keepWithNext=True))
styles.add(ParagraphStyle(name="BodyTextCustom", fontName="Arial", fontSize=9.2, leading=12.4, textColor=TEXT, spaceAfter=5))
styles.add(ParagraphStyle(name="HeadingOneCustom", fontName="Arial-Bold", fontSize=15, leading=18, textColor=BLUE, spaceBefore=12, spaceAfter=6, keepWithNext=True))
styles.add(ParagraphStyle(name="HeadingTwoCustom", fontName="Arial-Bold", fontSize=11, leading=14, textColor=colors.HexColor("#24527A"), spaceBefore=8, spaceAfter=4, keepWithNext=True))
styles.add(ParagraphStyle(name="TableTextCustom", fontName="Arial", fontSize=8.1, leading=10.4, textColor=TEXT))
styles.add(ParagraphStyle(name="TableHeaderCustom", fontName="Arial-Bold", fontSize=8.1, leading=10.4, textColor=BLUE))
styles.add(ParagraphStyle(name="CalloutCustom", fontName="Arial", fontSize=9, leading=12, textColor=TEXT, leftIndent=5, rightIndent=5))

def inline_markup(paragraph):
    chunks = []
    for run in paragraph.runs:
        text = escape(run.text).replace("\n", "<br/>")
        if not text:
            continue
        if run.bold and run.italic:
            text = f"<b><i>{text}</i></b>"
        elif run.bold:
            text = f"<b>{text}</b>"
        elif run.italic:
            text = f"<i>{text}</i>"
        chunks.append(text)
    return "".join(chunks) or escape(paragraph.text).replace("\n", "<br/>")

def paragraph_style(paragraph):
    name = paragraph.style.name if paragraph.style else "Normal"
    if name == "Title":
        return styles["DocTitle"]
    if name == "Heading 1":
        return styles["HeadingOneCustom"]
    if name.startswith("Heading 2"):
        return styles["HeadingTwoCustom"]
    return styles["BodyTextCustom"]

def make_cell(cell, header=False):
    parts = []
    for p in cell.paragraphs:
        if p.text.strip():
            parts.append(inline_markup(p))
    content = "<br/>".join(parts) or " "
    return Paragraph(content, styles["TableHeaderCustom" if header else "TableTextCustom"])

def build_table(table, index):
    data = []
    for row_index, row in enumerate(table.rows):
        data.append([make_cell(cell, header=(row_index == 0 and index in (1, 2))) for cell in row.cells])
    if index == 1:
        widths = [1.2 * inch, 1.3 * inch, 1.35 * inch, PAGE_WIDTH - 3.85 * inch]
    elif index == 2:
        widths = [1.65 * inch, 1.25 * inch, 1.25 * inch, PAGE_WIDTH - 4.15 * inch]
    else:
        widths = [PAGE_WIDTH]
    result = Table(data, colWidths=widths, repeatRows=1 if index in (1, 2) else 0, hAlign="LEFT")
    commands = [
        ("GRID", (0, 0), (-1, -1), 0.45, GRID),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 6),
        ("RIGHTPADDING", (0, 0), (-1, -1), 6),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
    ]
    if index in (1, 2):
        commands += [("BACKGROUND", (0, 0), (-1, 0), MID_BLUE)]
        for r in range(1, len(data)):
            if r % 2 == 0:
                commands.append(("BACKGROUND", (0, r), (-1, r), LIGHT_BLUE))
    else:
        commands += [("BACKGROUND", (0, 0), (-1, -1), LIGHT_BLUE), ("BOX", (0, 0), (-1, -1), 0.7, colors.HexColor("#7E9BB3"))]
    result.setStyle(TableStyle(commands))
    return result

def iter_body(document):
    body = document.element.body
    for child in body.iterchildren():
        if child.tag.endswith("}p"):
            yield DocxParagraph(child, document)
        elif child.tag.endswith("}tbl"):
            yield DocxTable(child, document)

docx = Document(str(SOURCE))
section = docx.sections[0]
header_text = " ".join(p.text for p in section.header.paragraphs).strip()
footer_text = " ".join(p.text for p in section.footer.paragraphs).strip().replace("\ufffd", "|")

def draw_page(canvas, document):
    canvas.saveState()
    canvas.setFont("Arial-Bold", 7.5)
    canvas.setFillColor(colors.HexColor("#526577"))
    canvas.drawString(document.leftMargin, letter[1] - 0.46 * inch, header_text)
    canvas.setStrokeColor(colors.HexColor("#CBD5DF"))
    canvas.setLineWidth(0.5)
    canvas.line(document.leftMargin, letter[1] - 0.54 * inch, letter[0] - document.rightMargin, letter[1] - 0.54 * inch)
    canvas.setFont("Arial", 7.5)
    canvas.setFillColor(colors.HexColor("#657789"))
    canvas.drawString(document.leftMargin, 0.38 * inch, footer_text)
    canvas.drawRightString(letter[0] - document.rightMargin, 0.38 * inch, f"Page {document.page}")
    canvas.restoreState()

story = []
table_index = 0
items = list(iter_body(docx))
i = 0
while i < len(items):
    item = items[i]
    if isinstance(item, DocxTable):
        story.append(build_table(item, table_index))
        story.append(Spacer(1, 8))
        table_index += 1
        i += 1
        continue

    if not item.text.strip():
        i += 1
        continue
    style_name = item.style.name if item.style else "Normal"
    if style_name == "Subtitle":
        style = styles["Subtitle"]
    elif style_name.startswith("List Bullet"):
        style = styles["BodyTextCustom"]
        story.append(Paragraph(inline_markup(item), style, bulletText="•"))
        i += 1
        continue
    else:
        style = paragraph_style(item)

    # Keep each functional test heading and its setup, steps, and expected result together when possible.
    if style_name.startswith("Heading 2") and item.text.startswith("LM-"):
        group = [Paragraph(inline_markup(item), style)]
        j = i + 1
        while j < len(items) and isinstance(items[j], DocxParagraph) and not (items[j].style and items[j].style.name.startswith("Heading")):
            p = items[j]
            if p.text.strip():
                group.append(Paragraph(inline_markup(p), paragraph_style(p)))
            j += 1
        story.append(KeepTogether(group))
        i = j
        continue

    story.append(Paragraph(inline_markup(item), style))
    i += 1

pdf = SimpleDocTemplate(
    str(OUTPUT), pagesize=letter,
    leftMargin=0.72 * inch, rightMargin=0.72 * inch,
    topMargin=0.76 * inch, bottomMargin=0.68 * inch,
    title="Leave Management Feature Test Plan",
    author="Chabrin HR Systems",
)
pdf.build(story, onFirstPage=draw_page, onLaterPages=draw_page)
print(OUTPUT)
