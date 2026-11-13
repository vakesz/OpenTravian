#!/usr/bin/env python3
"""Derives the Wonder of the World map tiles and village-centre click outlines from the Gpack building sprites.

    python3 dev/wonder-sprites/generate.py            # writes the map tiles, prints the outline PHP
    python3 dev/wonder-sprites/generate.py --check    # exits 1 when a committed tile differs

Source: assets/gpack/travian/mainPage/img_ltr/themes/default/buildings/<tribe>/wonder-of-the-world-<stage>.png
(299x440, the art standing on the bottom of the canvas). Stage 0..5 follows the Wonder level (see
WonderOfTheWorldModel::stage()).

Map tiles: game/deployment/public/img/map/wonder/tribe<id>/<n>x<n>/wonder<stage>.png for n = 60, 30, 6. The
art's opaque box is scaled to fit the tile, centred horizontally and set on the tile's bottom edge, like the
village sprites. Natars (tribe 5) have no Wonder art of their own and use the Roman one.

Outlines: one SVG path per tribe and stage in the 299x440 sprite space, traced from the alpha channel row by row
and simplified; printed as PHP for game/include/resources/Svg/buildFieldSvg.php.

Needs Pillow.
"""

import io
import sys
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[2]
SPRITES = ROOT / "assets/gpack/travian/mainPage/img_ltr/themes/default/buildings"
TILES = ROOT / "game/deployment/public/img/map/wonder"
TRIBE_ART = {1: "roman", 2: "teuton", 3: "gaul", 5: "roman", 6: "egyptian", 7: "hun", 8: "spartan", 9: "viking"}
OUTLINE_TRIBES = ["roman", "teuton", "gaul", "egyptian", "hun", "spartan", "viking"]
SIZES = [60, 30, 6]
STAGES = range(6)
ALPHA_MIN = 24


def sprite(art: str, stage: int) -> Image.Image:
    return Image.open(SPRITES / art / f"wonder-of-the-world-{stage}.png").convert("RGBA")


def tile(image: Image.Image, size: int) -> bytes:
    box = image.getchannel("A").point(lambda a: 255 if a >= ALPHA_MIN else 0).getbbox()
    art = image.crop(box)
    scale = min(size / art.width, size / art.height)
    art = art.resize((max(1, round(art.width * scale)), max(1, round(art.height * scale))), Image.LANCZOS)
    out = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    out.alpha_composite(art, ((size - art.width) // 2, size - art.height))
    buffer = io.BytesIO()
    out.save(buffer, "PNG", optimize=True)
    return buffer.getvalue()


def simplify(points, tolerance):
    """Ramer-Douglas-Peucker on an open polyline."""
    if len(points) < 3:
        return points
    (x1, y1), (x2, y2) = points[0], points[-1]
    dx, dy = x2 - x1, y2 - y1
    length = (dx * dx + dy * dy) ** 0.5 or 1.0
    index, distance = 0, -1.0
    for i in range(1, len(points) - 1):
        px, py = points[i]
        d = abs(dy * px - dx * py + x2 * y1 - y2 * x1) / length
        if d > distance:
            index, distance = i, d
    if distance <= tolerance:
        return [points[0], points[-1]]
    return simplify(points[: index + 1], tolerance)[:-1] + simplify(points[index:], tolerance)


def outline(image: Image.Image) -> str:
    alpha = image.getchannel("A")
    width, height = image.size
    data = alpha.load()
    left, right = [], []
    for y in range(0, height, 2):
        xs = [x for x in range(width) if data[x, y] >= ALPHA_MIN]
        if xs:
            left.append((xs[0], y))
            right.append((xs[-1] + 1, y))
    if not left:
        return ""
    left = simplify(left, 2.0)
    right = simplify(right, 2.0)
    points = left + list(reversed(right))
    return "M" + " L".join(f"{x} {y}" for x, y in points) + " Z"


def main() -> int:
    check = "--check" in sys.argv
    stale = []
    for tribe, art in TRIBE_ART.items():
        for stage in STAGES:
            image = sprite(art, stage)
            for size in SIZES:
                path = TILES / f"tribe{tribe}" / f"{size}x{size}" / f"wonder{stage}.png"
                data = tile(image, size)
                if check:
                    if not path.is_file() or path.read_bytes() != data:
                        stale.append(str(path.relative_to(ROOT)))
                    continue
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_bytes(data)
    if check:
        for path in stale:
            print(f"stale: {path}")
        return 1 if stale else 0
    print("// Wonder of the World click outlines per tribe and stage, 299x440 sprite space (dev/wonder-sprites/generate.py).")
    for art in OUTLINE_TRIBES:
        for stage in STAGES:
            print(f"$buildingField['g40']['{art}'][{stage}]='{outline(sprite(art, stage))}';")
    return 0


if __name__ == "__main__":
    sys.exit(main())
