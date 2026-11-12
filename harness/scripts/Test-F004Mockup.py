"""Static checks only; this script never claims browser or WCAG validation."""
from html.parser import HTMLParser
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / "mockups/f004"


class Markup(HTMLParser):
    def __init__(self):
        super().__init__()
        self.ids = set()
        self.anchors = []
        self.labels = 0
        self.controls = 0
        self.labelled_controls = 0
        self.label_depth = 0
        self.references = []

    def handle_starttag(self, tag, attributes):
        a = dict(attributes)
        if "id" in a:
            assert a["id"] not in self.ids, f"Duplicate id: {a['id']}"
            self.ids.add(a["id"])
        if tag == "label":
            self.labels += 1
            self.label_depth += 1
        if tag in ("input", "select", "textarea"):
            self.controls += 1
            self.labelled_controls += bool(self.label_depth or a.get("aria-label") or a.get("aria-labelledby"))
        if tag == "img":
            assert a.get("alt"), "Missing image alternative"
        if a.get("aria-labelledby"):
            self.anchors.extend(a["aria-labelledby"].split())
        for name in ("href", "src"):
            value = a.get(name, "")
            if value.startswith("#"):
                self.anchors.append(value[1:])
            elif value:
                assert ":" not in value and not value.startswith("//"), "External resource"
                self.references.append(value)

    def handle_endtag(self, tag):
        if tag == "label":
            self.label_depth -= 1


def luminance(hex_color):
    rgb = [int(hex_color[i:i+2], 16) / 255 for i in (1, 3, 5)]
    linear = [c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4 for c in rgb]
    return sum(c * weight for c, weight in zip(linear, (0.2126, 0.7152, 0.0722)))


def contrast(foreground, background):
    a, b = sorted((luminance(foreground), luminance(background)))
    return (b + 0.05) / (a + 0.05)


def main():
    markup = Markup()
    content = (ROOT / "index.html").read_text(encoding="utf-8")
    markup.feed(content)
    assert markup.controls == markup.labelled_controls, "Unlabelled form control"
    assert all(anchor in markup.ids for anchor in markup.anchors), "Broken local anchor"
    assert all((ROOT / resource).is_file() for resource in markup.references), "Missing local resource"
    assert 'lang="es"' in content and 'user-scalable=no' not in content and 'maximum-scale=1' not in content
    css = (ROOT / "style.css").read_text(encoding="utf-8")
    assert ":focus-visible" in css and "max-width:760px" in css and "prefers-reduced-motion" in css
    for foreground, background, minimum in (
        ("#0b1f3a", "#ffffff", 4.5), ("#ffffff", "#0057ff", 4.5),
        ("#526176", "#ffffff", 4.5), ("#526176", "#edf3ff", 4.5),
        ("#08763e", "#e8f6ed", 4.5), ("#8c4a04", "#fff2df", 4.5),
        ("#dce6f6", "#0b1f3a", 4.5), ("#00d4ff", "#0b1f3a", 3),
        ("#7a8ca6", "#ffffff", 3),
    ):
        ratio = contrast(foreground, background)
        assert ratio >= minimum, f"Contrast {foreground}/{background}: {ratio:.2f} < {minimum}"
        print(f"PASS contrast {foreground}/{background}: {ratio:.2f}:1")
    print(f"PASS markup: {markup.controls} labelled controls; unique IDs; local anchors/assets; image alternatives")
    print("PASS static CSS declarations: focus, mobile breakpoint, reduced motion; zoom not restricted")
    print("LIMIT: no browser, keyboard, visual layout or screen-reader verification")


if __name__ == "__main__":
    main()
