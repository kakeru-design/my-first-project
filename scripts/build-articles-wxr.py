#!/usr/bin/env python3
"""docs/articles/<site>/*.md から、WordPress の「ツール → インポート → WordPress」で
取り込める WXR ファイル(投稿一式)を dist/<site>-articles.xml に作る。

使い方: python3 scripts/build-articles-wxr.py uranai adult

- 先頭の「# 見出し」が記事タイトル(本文からは除く)。
- 最初の段落を抜粋(検索結果に出る説明文)にする。
- スラッグはファイル名。カテゴリは SITES の category。
- 取り込むと「公開」状態になる。同じスラッグの記事がすでにあれば、WordPress は重複として飛ばす。
"""
import datetime
import html
import importlib.util
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
spec = importlib.util.spec_from_file_location("legal", ROOT / "scripts/build-legal-wxr.py")
legal = importlib.util.module_from_spec(spec)
spec.loader.exec_module(legal)

SITES = {
    "uranai": {"title": "運勢日和", "url": "https://unsei-biyori.com", "category": ("kaiun-column", "開運コラム")},
    "adult": {"title": "ファンズピック", "url": "https://fanz-pick.com", "category": ("guide", "FANZAの使い方")},
}


def excerpt(md):
    for block in re.split(r"\n\s*\n", md):
        b = block.strip()
        if b and not b.startswith("#"):
            text = re.sub(r"\[([^\]]+)\]\([^)]+\)", r"\1", b.replace("**", ""))
            return text[:118] + ("…" if len(text) > 118 else "")
    return ""


def build(site):
    conf = SITES[site]
    cat_slug, cat_name = conf["category"]
    now = datetime.datetime.now()
    items = []
    for n, path in enumerate(sorted((ROOT / "docs/articles" / site).glob("*.md")), start=1):
        md = path.read_text()
        title = re.search(r"^#\s+(.+)$", md, re.M).group(1).strip()
        body = legal.md_to_html(md)
        date = (now - datetime.timedelta(minutes=n)).strftime("%Y-%m-%d %H:%M:%S")
        items.append(f"""
  <item>
    <title>{html.escape(title)}</title>
    <dc:creator>{legal.cdata("admin")}</dc:creator>
    <content:encoded>{legal.cdata(body)}</content:encoded>
    <excerpt:encoded>{legal.cdata(excerpt(md))}</excerpt:encoded>
    <wp:post_id>{8000 + n}</wp:post_id>
    <wp:post_date>{legal.cdata(date)}</wp:post_date>
    <wp:comment_status>{legal.cdata("closed")}</wp:comment_status>
    <wp:ping_status>{legal.cdata("closed")}</wp:ping_status>
    <wp:post_name>{legal.cdata(path.stem)}</wp:post_name>
    <wp:status>{legal.cdata("publish")}</wp:status>
    <wp:post_parent>0</wp:post_parent>
    <wp:menu_order>0</wp:menu_order>
    <wp:post_type>{legal.cdata("post")}</wp:post_type>
    <wp:post_password>{legal.cdata("")}</wp:post_password>
    <wp:is_sticky>0</wp:is_sticky>
    <category domain="category" nicename="{cat_slug}">{legal.cdata(cat_name)}</category>
  </item>""")
        print(f"  {path.stem:30s} {title}")

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
  <wp:author><wp:author_login>{legal.cdata("admin")}</wp:author_login><wp:author_display_name>{legal.cdata("admin")}</wp:author_display_name></wp:author>
  <wp:category><wp:category_nicename>{legal.cdata(cat_slug)}</wp:category_nicename><wp:category_parent>{legal.cdata("")}</wp:category_parent><wp:cat_name>{legal.cdata(cat_name)}</wp:cat_name></wp:category>
{"".join(items)}
</channel>
</rss>
"""
    out = ROOT / "dist" / f"{site}-articles.xml"
    out.parent.mkdir(exist_ok=True)
    out.write_text(xml)
    print(f"-> {out.relative_to(ROOT)}")


if __name__ == "__main__":
    for s in sys.argv[1:] or ["uranai", "adult"]:
        build(s)
