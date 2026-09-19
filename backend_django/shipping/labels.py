"""
Étiquettes colis PDF (ROADMAP #56).
Génère une étiquette A6 avec QR code, expéditeur/destinataire, poids, numéro suivi.
"""
import io
import qrcode
from django.http import HttpResponse
from reportlab.lib.pagesizes import A6
from reportlab.lib.units import mm
from reportlab.pdfgen import canvas
from reportlab.lib.utils import ImageReader


def _draw_wrapped(c: canvas.Canvas, text: str, x: float, y: float, max_width: float, font="Helvetica", size=10, leading=12):
    c.setFont(font, size)
    words = text.split()
    line = ""
    cy = y
    for w in words:
        test = (line + " " + w).strip()
        if c.stringWidth(test, font, size) <= max_width:
            line = test
        else:
            c.drawString(x, cy, line)
            cy -= leading
            line = w
    if line:
        c.drawString(x, cy, line)
        cy -= leading
    return cy


def label_pdf(colis) -> HttpResponse:
    buf = io.BytesIO()
    c = canvas.Canvas(buf, pagesize=A6)
    w, h = A6

    # Entête
    c.setFillColorRGB(0.2, 0.6, 0.86)
    c.rect(0, h - 18 * mm, w, 18 * mm, stroke=0, fill=1)
    c.setFillColorRGB(1, 1, 1)
    c.setFont("Helvetica-Bold", 14)
    c.drawString(8 * mm, h - 11 * mm, "SPIISTMOVE")
    c.setFont("Helvetica", 9)
    c.drawRightString(w - 8 * mm, h - 11 * mm, "Étiquette colis")

    # QR code
    qr = qrcode.QRCode(border=2, box_size=4)
    url = f"/suivi?numero_suivi={colis.numero_suivi}"
    qr.add_data(url)
    qr.make(fit=True)
    img = qr.make_image(fill_color="black", back_color="white")
    img_buf = io.BytesIO()
    img.save(img_buf, format="PNG")
    img_buf.seek(0)
    ir = ImageReader(img_buf)
    qr_size = 35 * mm
    c.drawImage(ir, w - qr_size - 8 * mm, h - 18 * mm - qr_size - 5 * mm, qr_size, qr_size)

    # Numéro suivi
    c.setFillColorRGB(0.1, 0.1, 0.1)
    c.setFont("Helvetica-Bold", 18)
    c.drawString(8 * mm, h - 30 * mm, colis.numero_suivi)

    # Expéditeur / Destinataire
    cy = h - 40 * mm
    c.setFont("Helvetica-Bold", 10)
    c.setFillColorRGB(0.4, 0.4, 0.4)
    c.drawString(8 * mm, cy, "EXPÉDITEUR")
    cy -= 5 * mm
    cy = _draw_wrapped(c, f"{colis.user.prenom or ''} {colis.user.nom or ''}".strip(), 8 * mm, cy, 70 * mm)
    cy = _draw_wrapped(c, colis.user.email or "", 8 * mm, cy, 70 * mm, size=8)

    cy -= 4 * mm
    c.setFillColorRGB(0.4, 0.4, 0.4)
    c.setFont("Helvetica-Bold", 10)
    c.drawString(8 * mm, cy, "DESTINATAIRE")
    cy -= 5 * mm
    dest_lines = [
        f"{colis.ville}, {colis.pays}",
        colis.adresse_destination or "",
    ]
    for line in dest_lines:
        if line:
            cy = _draw_wrapped(c, line, 8 * mm, cy, 70 * mm)

    # Infos colis
    c.setStrokeColorRGB(0.85, 0.85, 0.85)
    c.line(8 * mm, 28 * mm, w - 8 * mm, 28 * mm)
    c.setFont("Helvetica", 9)
    c.setFillColorRGB(0.2, 0.2, 0.2)
    c.drawString(8 * mm, 22 * mm, f"Colis : {colis.nom_colis}")
    c.drawString(8 * mm, 17 * mm, f"Poids : {colis.poids} kg")
    c.drawString(8 * mm, 12 * mm, f"Type : {colis.type_produit}")
    c.drawRightString(w - 8 * mm, 12 * mm, f"Créé le {colis.date_creation.strftime('%d/%m/%Y')}")

    # Barre bas
    c.setFillColorRGB(0.1, 0.5, 0.3)
    c.rect(0, 0, w, 6 * mm, stroke=0, fill=1)
    c.setFillColorRGB(1, 1, 1)
    c.setFont("Helvetica-Bold", 8)
    c.drawCentredString(w / 2, 2 * mm, "Merci de scanner le QR code pour suivre la livraison")

    c.showPage()
    c.save()

    response = HttpResponse(buf.getvalue(), content_type="application/pdf")
    response["Content-Disposition"] = f'attachment; filename="etiquette-{colis.numero_suivi}.pdf"'
    return response
