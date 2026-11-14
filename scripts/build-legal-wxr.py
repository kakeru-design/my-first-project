#!/usr/bin/env python3
"""docs/legal/<site>/*.md から、WordPress の「ツール → インポート → WordPress」で
取り込める WXR ファイル(固定ページ一式)を dist/ に作る。

使い方: python3 scripts/build-legal-wxr.py adult
        python3 scripts/build-legal-wxr.py uranai

- 各 Markdown の先頭の「# 見出し」がページタイトルになる(本文からは除く)。
- 「## フォームの入力項目」の節は設置時のメモなので、本文から除く。
- 【 】の未記入項目が残っているページは「下書き」で取り込む(公開前に埋めてもらうため)。
"""
import datetime
import html
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent

SITES = {
    "adult": {
        "title": "ファンズピック",
        "url": "https://fanz-pick.com",
        "pages": ["operator", "privacy-policy", "disclaimer", "advertising", "age-policy", "contact"],
    },
    "uranai": {
        "title": "運勢日和",
        "url": "https://unsei-biyori.com",
        "pages": ["operator", "privacy-policy", "disclaimer", "advertising", "contact"],
    },
}

SKIP_SECTIONS = ("フォームの入力項目",)


def inline(text):
    text = html.escape(text, quote=False)
    text = re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", text)
    text = re.sub(r"\[([^\]]+)\]\(([^)\s]+)\)", lambda m: f'<a href="{html.escape(m.group(2))}">{m.group(1)}</a>', text)
    return text


def md_to_html(md):
    lines = md.splitlines()
    out, para, i = [], [], 0

    def flush():
        if para:
            out.append("<p>" + "<br>".join(inline(p) for p in para) + "</p>")
            para.clear()

    skipping = False
    while i < len(lines):
        line = lines[i].rstrip()
        m = re.match(r"^(#{1,6})\s+(.*)", line)
        if m:
            flush()
            level, text = len(m.group(1)), m.group(2)
            skipping = level == 2 and any(k in text for k in SKIP_SECTIONS)
            if not skipping and level > 1:
                out.append(f"<h{level}>{inline(text)}</h{level}>")
            i += 1
            continue
        if skipping:
            i += 1
            continue
        if not line.strip():
            flush()
        elif line.startswith("|"):
            flush()
            rows = []
            while i < len(lines) and lines[i].startswith("|"):
                cells = [c.strip() for c in lines[i].strip().strip("|").split("|")]
                if not all(re.fullmatch(r":?-{3,}:?", c) for c in cells):
                    rows.append(cells)
                i += 1
            t = ["<table>"]
            for r, cells in enumerate(rows):
                tag = "th" if r == 0 else "td"
                t.append("<tr>" + "".join(f"<{tag}>{inline(c)}</{tag}>" for c in cells) + "</tr>")
            out.append("".join(t) + "</table>")
            continue
        elif re.match(r"^\s*([-*]|\d+\.)\s+", line):
            flush()
            ordered = bool(re.match(r"^\s*\d+\.", line))
            items = []
            while i < len(lines) and re.match(r"^\s*([-*]|\d+\.)\s+", lines[i]):
                items.append(re.sub(r"^\s*([-*]|\d+\.)\s+", "", lines[i]))
                i += 1
            tag = "ol" if ordered else "ul"
            out.append(f"<{tag}>" + "".join(f"<li>{inline(x)}</li>" for x in items) + f"</{tag}>")
            continue
        elif line.startswith(">"):
            flush()
            out.append(f"<blockquote><p>{inline(line.lstrip('> '))}</p></blockquote>")
        elif re.fullmatch(r"-{3,}|\*{3,}", line.strip()):
            flush()
            out.append("<hr>")
        else:
            para.append(line.strip())
        i += 1
    flush()
    return "\n".join(out)


def cdata(s):
    return "<![CDATA[" + s.replace("]]>", "]]]]><![CDATA[>") + "]]>"


def build(site):
    conf = SITES[site]
    now = datetime.datetime.now()
    items = []
    for n, slug in enumerate(conf["pages"], start=1):
        md = (ROOT / "docs/legal" / site / f"{slug}.md").read_text()
        title = re.search(r"^#\s+(.+)$", md, re.M).group(1).strip()
        body = md_to_html(md)
        status = "draft" if "【" in body else "publish"
        items.append(f"""
  <item>
    <title>{html.escape(title)}</title>
    <dc:creator>{cdata("admin")}</dc:creator>
    <content:encoded>{cdata(body)}</content:encoded>
    <excerpt:encoded>{cdata("")}</excerpt:encoded>
    <wp:post_id>{9000 + n}</wp:post_id>
    <wp:post_date>{cdata(now.strftime("%Y-%m-%d %H:%M:%S"))}</wp:post_date>
    <wp:comment_status>{cdata("closed")}</wp:comment_status>
    <wp:ping_status>{cdata("closed")}</wp:ping_status>
    <wp:post_name>{cdata(slug)}</wp:post_name>
    <wp:status>{cdata(status)}</wp:status>
    <wp:post_parent>0</wp:post_parent>
    <wp:menu_order>{n}</wp:menu_order>
    <wp:post_type>{cdata("page")}</wp:post_type>
    <wp:post_password>{cdata("")}</wp:post_password>
    <wp:is_sticky>0</wp:is_sticky>
  </item>""")
        print(f"  {slug:15s} {status:8s} {title}")

    xml = f"""<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
  xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
  xmlns:content="http://purl.org/rss/1.0/modules/content/"
  xmlns:wfw="http://wellformedweb.org/CommentAPI/"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
  <title>{html.escape(conf["title"])}</title>
  <link>{conf["url"]}</link>
  <language>ja</language>
  <wp:wxr_version>1.2</wp:wxr_version>
  <wp:base_site_url>{conf["url"]}</wp:base_site_url>
  <wp:base_blog_url>{conf["url"]}</wp:base_blog_url>
  <wp:author><wp:author_login>{cdata("admin")}</wp:author_login><wp:author_display_name>{cdata("admin")}</wp:author_display_name></wp:author>
{"".join(items)}
</channel>
</rss>
"""
    out = ROOT / "dist" / f"{site}-pages.xml"
    out.parent.mkdir(exist_ok=True)
    out.write_text(xml)
    print(f"-> {out.relative_to(ROOT)}")


if __name__ == "__main__":
    for s in sys.argv[1:] or ["adult"]:
        build(s)
