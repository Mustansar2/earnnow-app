import os
import math
from PIL import Image, ImageDraw, ImageFont

icons_dir = r"d:\Wordpress\EarnNow\assets\icons"
os.makedirs(icons_dir, exist_ok=True)

def create_pwa_icon(size, is_maskable=False):
    # Create high-res RGBA image
    img = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)

    # Background
    if is_maskable:
        # Full bleed background for maskable icons
        for y in range(size):
            ratio = y / size
            r = int(15 * (1 - ratio) + 16 * ratio)
            g = int(23 * (1 - ratio) + 185 * ratio)
            b = int(42 * (1 - ratio) + 129 * ratio)
            draw.line([(0, y), (size, y)], fill=(r, g, b, 255))
    else:
        # Rounded squircle container
        corner_radius = int(size * 0.22)
        mask = Image.new("L", (size, size), 0)
        mask_draw = ImageDraw.Draw(mask)
        mask_draw.rounded_rectangle([(0, 0), (size - 1, size - 1)], radius=corner_radius, fill=255)
        
        grad = Image.new("RGBA", (size, size))
        grad_draw = ImageDraw.Draw(grad)
        for y in range(size):
            ratio = y / size
            r = int(15 * (1 - ratio) + 16 * ratio)
            g = int(23 * (1 - ratio) + 185 * ratio)
            b = int(42 * (1 - ratio) + 129 * ratio)
            grad_draw.line([(0, y), (size, y)], fill=(r, g, b, 255))
            
        img.paste(grad, (0, 0), mask)

    # Foreground Icon Graphic: Stylized "E" with growth / arrow accents
    center = size / 2
    scale = size / 512.0
    
    # Outer glowing ring / circle accent
    ring_radius = int(140 * scale if is_maskable else 160 * scale)
    
    ring_bbox = [
        center - ring_radius,
        center - ring_radius,
        center + ring_radius,
        center + ring_radius
    ]
    draw.ellipse(ring_bbox, outline=(52, 211, 153, 180), width=int(12 * scale))

    # Inner Emblem - Golden/Emerald bold 'E' and upward chevron
    # Draw stylized upward arrow bar
    arrow_pts = [
        (center - 70 * scale, center + 70 * scale),
        (center - 10 * scale, center + 10 * scale),
        (center + 30 * scale, center + 45 * scale),
        (center + 85 * scale, center - 40 * scale),
    ]
    draw.line(arrow_pts, fill=(245, 158, 11, 255), width=int(22 * scale), joint="curve")
    
    # Arrowhead
    head_pts = [
        (center + 50 * scale, center - 40 * scale),
        (center + 95 * scale, center - 50 * scale),
        (center + 85 * scale, center - 5 * scale)
    ]
    draw.polygon(head_pts, fill=(245, 158, 11, 255))

    # Bold "E" Letter glyph
    e_left = center - 80 * scale
    e_top = center - 90 * scale
    bar_thick = int(26 * scale)
    
    # Vertical stem
    draw.rounded_rectangle([e_left, e_top, e_left + bar_thick, center + 90 * scale], radius=int(6*scale), fill=(255, 255, 255, 255))
    # Top bar
    draw.rounded_rectangle([e_left, e_top, e_left + 120 * scale, e_top + bar_thick], radius=int(6*scale), fill=(255, 255, 255, 255))
    # Middle bar
    draw.rounded_rectangle([e_left, center - bar_thick/2, e_left + 90 * scale, center + bar_thick/2], radius=int(6*scale), fill=(255, 255, 255, 255))
    # Bottom bar
    draw.rounded_rectangle([e_left, center + 90 * scale - bar_thick, e_left + 120 * scale, center + 90 * scale], radius=int(6*scale), fill=(255, 255, 255, 255))

    return img

# Generate all required sizes
sizes = {
    "icon-192x192.png": (192, False),
    "icon-512x512.png": (512, False),
    "icon-maskable-512x512.png": (512, True),
    "apple-touch-icon.png": (180, False),
    "favicon.png": (48, False),
}

for filename, (s, maskable) in sizes.items():
    icon = create_pwa_icon(s, maskable)
    filepath = os.path.join(icons_dir, filename)
    icon.save(filepath, "PNG", optimize=True)
    print(f"Generated {filepath} ({s}x{s})")

print("All icons successfully created!")
