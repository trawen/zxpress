#!/usr/bin/env python3
"""Move inline style= attributes from public Smarty templates to CSS classes."""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
TPL_DIR = ROOT / "site/smarty/zxpress/templates"
CSS_FILES = [ROOT / "site/img/style.css", ROOT / "site/style.css"]
CSS_MARKER = "/* site components (migrated from inline styles) */"

SKIP_FILES = {
    "admin_pub_articles.tpl",
    "admin_publications.tpl",
    "admin_top.tpl",
    "admin_letters.tpl",
    "admin_authors.tpl",
    "admin_books_light.tpl",
    "admin_books.tpl",
    "admin_articles.tpl",
    "admin_news.tpl",
    "admin_tags.tpl",
    "admin_issue.tpl",
    "gallery_admin.tpl",
}

# Longest keys first when applying.
STYLE_TO_CLASS: dict[str, str] = {
    'style="background:none;border:none;padding:0;font:inherit;cursor:pointer;color:inherit;text-decoration:underline"': 'class="btn-link"',
    "style='background:none;border:none;padding:0;font:inherit;cursor:pointer;color:inherit;text-decoration:underline'": 'class="btn-link"',
    'style="background-color: black; color: white; border: 1px solid #c5c1ac; font: bold 12px Times"': 'class="btn-auth"',
    'style="border: 1px solid #c5c1ac; background-color: #F2EFDE; width: 60px"': 'class="input-auth"',
    'style="border: 1px solid #c5c1ac; background-color: #F6F6F6; width: 150px"': 'class="input-comment"',
    'style="background-color: #F6F6F6; border: 1px solid #c5c1ac; width: 150px; background-image: url(confirm_code.php?token={$captcha_token}); background-repeat: no-repeat;"': 'class="input-captcha"',
    'style="font-family: arial; font-size: 13px; border: 1px solid #c5c1ac; width: 500px; background-color: #F9F9F9"': 'class="textarea-comment"',
    'style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"': 'class="u-sr-only"',
    'style="position:relative;display:inline-block"': 'class="search-input-wrap"',
    'style="position:relative"': 'class="search-input-wrap"',
    'style="display:inline;margin:0"': 'class="form-inline"',
    'style="display:inline"': 'class="u-inline"',
    'style="display: inline"': 'class="u-inline"',
    'style="display:inline-block;width:640px;height:90px"': 'class="u-hidden"',
    'style="display: none"': 'class="u-hidden"',
    'style="display: flex;"': 'class="updates-rule-row"',
    'style="flex: 0 0 100px;"': 'class="updates-rule-spacer"',
    'style="width:100%"': 'class="updates-rule-line"',
    'style="clear: both"': 'class="u-clearfix"',
    'style="clear:both;"': 'class="u-clearfix"',
    'style="clear:both"': 'class="u-clearfix"',
    'style="text-align: left"': 'class="u-text-left"',
    'style="opacity: 0.5"': 'class="u-faded"',
    'style="opacity: 0.1;"': 'class="right-counter-faded"',
    'style="filter:progid:DXImageTransform.Microsoft.Alpha(opacity=20); opacity: 0.1;"': 'class="right-counter-faded"',
    'style="color: inherit"': 'class="u-link-inherit"',
    'style="color:black"': 'class="u-link-black"',
    'style="color: black"': 'class="u-link-black"',
    'style="color: red"': 'class="msg-error-inline"',
    'style="color: green"': 'class="msg-success"',
    'style="color: #888"': 'class="pub-muted"',
    'style="color: #a41e00"': 'class="msg-warn"',
    'style="color: #a41e00; margin-bottom: 1em"': 'class="msg-warn-block"',
    'style="COLOR: red;"': 'class="msg-error"',
    'style="color: red;"': 'class="msg-error"',
    'style="margin-top: 1em"': 'class="u-mt-1em"',
    'style="margin-bottom: 1em"': 'class="u-mb-1em"',
    'style="padding: 4px"': 'class="pad-4"',
    'style="padding-left: 32px"': 'class="comments-wrap"',
    'style="padding-left:16 px; font: normal 13px Arial"': 'class="comments-table"',
    'style="padding-right: 8px"': 'class="cell-pr-8"',
    'style="padding-right: 8px;"': 'class="cell-pr-8"',
    'style="padding-right: 4px"': 'class="issue-download-icon"',
    'style="padding-left: 56px"': 'class="catalog-page-title-wrap"',
    'style="padding-left: 8px"': 'class="catalog-books-title-wrap"',
    'style="padding-left: 16px"': 'class="book-meta"',
    'style="padding-top: 8px; font: 13pt/10pt"': 'class="book-subtitle"',
    'style="padding-top: 6px"': 'class="book-meta-row"',
    'style="padding-top: 4px"': 'class="book-toc-item"',
    'style="padding-top: 10px; font: normal 13pt Times; width: 90%; padding-left: 8px"': 'class="guestbook-comment"',
    'style="font: normal 13pt Times; padding-bottom: 3px"': 'class="guestbook-comment-header"',
    'style="padding-bottom: 8px"': 'class="tag-date-pad"',
    'style="padding-bottom: 2px; padding-left: 8px"': 'class="articles-row"',
    'style="padding-bottom: 2px; padding-left: 8px; font: 10pt Verdana; color: black"': 'class="articles-sape"',
    'style="font: bold 10pt Verdana;"': 'class="book-meta-label"',
    'style="font: bold 10pt verdana"': 'class="book-file-name"',
    'style="font: bold 8pt verdana; color: #999"': 'class="book-file-meta"',
    'style="float: left; padding: 5px 5px 4px 6px;"': 'class="book-file-tile"',
    'style="font: bold 12pt Georgia"': 'class="book-toc-heading"',
    'style="font: 10pt/12pt Verdana; padding-left: 16px"': 'class="book-toc-link"',
    'style="font: bold 13pt Georgia"': 'class="article-related-heading"',
    'style="font: bold 13pt Georgia">Other articles:' : 'class="article-related-heading">Other articles:',
    'style="font: 13pt Georgia; color: #800;"': 'class="link-issue"',
    'style="height: 20px; float: right; padding-right: 10px"': 'class="article-toolbar-item"',
    'style="height: 20px; float: right; padding-right: 16px"': 'class="article-toolbar-item article-toolbar-item--tags"',
    'style="height: 20px; width: 13px; background: url(\'/img/tag1.png\') 100% 100% no-repeat; float: left;"': 'class="article-tag-left"',
    'style="height: 20px; background: url(\'img/tag2.png\') 100% 100% repeat-x; float: left;"': 'class="article-tag-mid"',
    'style="height: 20px; width: 5px; background: url(\'img/tag3.png\') 100% 100%; float: left;"': 'class="article-tag-right"',
    'style="cursor: pointer"': 'class="u-clickable"',
    'style="justify-self: center;"': 'class="article-screen-wrap"',
    'style="width: 600;"': 'class="article-related-table"',
    'style="width: 600"': 'class="article-related-table"',
    'style="width: 600px;"': 'class="book-toc-wrap"',
    'style="width: 600; padding-left: 32px"': 'class="book-toc-wrap"',
    'style="width: 620px; overflow: hidden; padding-left: 8px"': 'class="zxnet-topic-body"',
    'style="width: 630px; overflow: auto"': 'class="chron-chart-wrap"',
    'style="max-width:100%;height:auto;padding-bottom:8px;border:1px solid #D6D0AB;background:#faf8f5"': 'class="chron-chart-img"',
    'style="font: normal 13pt Times;"': 'class="type-times-13"',
    'style="font: bold 13pt Times; text-align: center"': 'class="chron-year-nav"',
    'style="font: bold 15pt Times"': 'class="chron-section-title"',
    'style="font: normal 18px Times; padding-right: 20px"': 'class="chron-year-heading"',
    'style="font: normal 13pt Times; padding-right: 20px"': 'class="chron-date"',
    'style="font: bold 13pt Times;"': 'class="type-times-13-bold"',
    'style="font: normal 13pt Times"': 'class="type-times-13"',
    'style="font: bold 13pt/17pt Times;"': 'class="right-similar-heading"',
    'style="font: bold 13pt/17pt Times"': 'class="right-on-this-day-links"',
    'style="font: normal 13pt/17pt Times; word-wrap: break-word"': 'class="right-topics"',
    'style="font: normal 13pt/17pt Times;  padding-top: 8px; word-wrap: break-word"': 'class="right-similar-item"',
    'style="font: normal 13pt Times;"': 'class="right-on-this-day"',
    'style="white-space: nowrap"': 'class="u-nowrap"',
    'style=" border: none"': 'class="right-select-option"',
    'style="font: normal 12px Arial; color: #aaa"': 'class="tree-item-count"',
    'style="height: 22px"': 'class="tree-add-btn"',
    'style="font: 13pt/15pt Times; text-align: left"': 'class="type-article-link"',
    'style="padding-bottom: 8px; font: normal 12px Tahoma; color: #999"': 'class="rubrics-date"',
    'style="font: bold 12pt Georgia; padding-left: 8px"': 'class="articles-issue-title"',
    'style="font: 9pt Georgia; padding-right: 8px; color: #796C5F"': 'class="articles-date"',
    'style="font: 10pt Verdana;"': 'class="articles-link"',
    'style="font: normal 15pt Times;"': 'class="tag-press-title"',
    'style="display:inline"': 'class="u-inline"',
    'style="font: normal 13px Times"': 'class="catalog-cell-times"',
    'style="font: bold 14px Times"': 'class="catalog-th-times"',
    'style="font: bold 13px Verdana"': 'class="catalog-th-verdana"',
    'style="COLOR: #493C2F; font: bold 32px Times; position: relative; top: 10px"': 'class="catalog-letter"',
    'style=" font: bold 32px Times; position: relative; top: 10px"': 'class="catalog-letter"',
    'style="color: black; font: bold 14px Times"': 'class="catalog-book-title"',
    'style="font: bold 14px Times"': 'class="catalog-th-times"',
    'style="height: 4px"': 'class="catalog-spacer-4"',
    'style="height: 3px"': 'class="catalog-spacer-3"',
    'style="height: 2px"': 'class="catalog-spacer-2"',
    'style="height: 6px"': 'class="catalog-spacer-6"',
    'style="color: #777"': 'class="catalog-books-muted"',
    'style="border: 8px solid #242321; -webkit-border-radius: 2px;-moz-border-radius: 2px;border-radius: 2px;"': 'class="issue-cover"',
    'style="padding: 4px; Font: normal 10px Verdana"': 'class="issue-sape"',
    'style="display: inline; font-size: 15pt; "': 'class="issue-type-label"',
    'style="font-size: 13pt; line-height: 150%"': 'class="issue-meta"',
    'style="font: 12pt Georgia; color: #800; text-align: center"': 'class="issue-section-header"',
    'style="font: 10pt; color: #312C12"': 'class="issue-article-date"',
    'style="font: 13pt/14pt Times; text-align: left"': 'class="issue-article-link"',
    'style="width: 262px; height: 3px; background-image: url(img/border_top.png); background-repeat: repeat-x"': 'class="book-frame-top"',
    'style="width: 3px; height: 100%; background-image: url(img/border_left.png); background-repeat: repeat-y; padding-right: 2px"': 'class="book-frame-side book-frame-side--left"',
    'style="width: 3px; height: 100%; background-image: url(img/border_left.png); background-repeat: repeat-y; padding-left: 1px"': 'class="book-frame-side book-frame-side--right"',
    'style="padding-top: 1px; margin: 0px"': 'class="book-frame-cell"',
    'style="width: 262px; height: 3px; background-image: url(img/border_top.png); background-repeat: repeat-x; padding-bottom: 1px"': 'class="book-frame-bottom"',
    'style="font: 13px Arial;"': 'class="type-arial-13"',
    'style="font: 15pt Georgia;"': 'class="book-article-title"',
    'style="font: 10pt Georgia; color: #796C5F; text-align: right; padding-right: 8px;"': 'class="book-article-date"',
    'style="font: 10pt Verdana; width: 600; padding-left: 16px"': 'class="book-article-chapter"',
    'style="font: 10pt Verdana; padding-left: 16px"': 'class="book-article-tags"',
    "style='background-color: white; color: black; font: normal Arial 14px; text-align: left; padding: 32px; width: 600px; opacity: .7;'": 'class="chapter-palette"',
    "style='font: normal Times 16px; text-align: left; width: 600px;'": 'class="book-article-body"',
    'style="border:none; overflow:hidden; width:150px; height:21px;"': 'class="chapter-fb-iframe"',
    'style="font: normal 16px Times;"': 'class="book-article-page"',
    'style="font: normal 13pt/18pt Times"': 'class="book-article-page"',
    'style="padding-top: 0px; margin-top: 0px;"': 'class="print-body"',
    "style='font-size: 12pt; font-weight: normal; text-align: left; color: black'": 'class="print-text"',
    'style="display: block; margin: auto; max-width: 100%"': 'class="news-img"',
    'style="height: 500px"': 'class="map-canvas"',
    'style="line-height: 24px"': 'class="whois-content"',
    'style="font: italic 15px/25px Times; "': 'class="footer-disclaimer"',
    'style="font: normal 13pt/12pt Times; padding: 0px 0px 10px 10px"': 'class="footer-cell"',
    'style="font: normal 13pt/10pt Times"': 'class="footer-copy"',
    'style="padding: 8px; font: normal 15px Times"': 'class="books-cell"',
    'style="font: bold 17px times"': 'class="books-title-link"',
    'style="font: bold 14px times"': 'class="books-action-link"',
    'style="position: absolute; left: 870px; top: 4px"': 'class="input-user-badge-pos"',
    'style="font: bold 11px Verdana; "': 'class="input-user-label"',
    'style="width: 150px"': 'class="input-narrow"',
    'style="width: 600px;"': 'class="textarea-guestbook"',
    'style="width: 150px; background-image: url(confirm_code.php?token={$captcha_token}); background-repeat: no-repeat;"': 'class="input-captcha input-narrow"',
    'style="font: bold 10pt Georgia"': 'class="comments-form-heading"',
    'style="font: bold 12px Georgia"': 'class="form-label-georgia"',
    "style='border-top: 1px solid #dedbd8;'": 'class="comments-text-cell"',
    'style="font-size: 14px; margin: 8px 0 4px 0;"': 'class="letter-meta-from"',
    'style="font-size: 14px; margin: 0 0 8px 0;"': 'class="letter-meta-to"',
    'style="margin: 12px 0; font-size: 16px;"': 'class="letter-summary"',
    'style="margin-top: 20px;"': 'class="pub-images"',
    'style="margin-bottom: 16px;"': 'class="pub-image-item"',
    'style="width: 100%; max-width: 100%; height: auto; border: 1px solid #ccc;"': 'class="pub-image"',
    'style="margin-top: 20px; font-size: 16px; line-height: 1.5;"': 'class="pub-body"',
    'style="margin-bottom: 12px;"': 'class="letter-banner"',
    'style="max-width: 100%; height: auto;"': 'class="letter-banner-img"',
    'style="font-size: 13px; line-height: 1.8; margin-bottom: 16px;"': 'class="pub-filters"',
    'style="margin-bottom: 18px; padding-bottom: 12px;"': 'class="pub-list-item"',
    'style="padding-right: 12px;"': 'class="pub-list-cover-cell"',
    'style="width: 128px; max-width: 128px; height: auto; border: 1px solid #ccc;"': 'class="pub-list-thumb"',
    'style="width: 128px; height: 96px; background: #eee; border: 1px solid #ccc;"': 'class="letter-list-placeholder"',
    'style="margin-top: 4px; font-size: 11px; color: #666;"': 'class="pub-list-date"',
    'style="font-size: 16px; font-weight: bold;"': 'class="pub-list-title"',
    'style="margin-top: 6px; font-size: 16px;"': 'class="pub-list-summary pub-list-summary--lg"',
    'style="margin-top: 6px; font-size: 14px; color: #555;"': 'class="letter-list-correspondents"',
    'style="margin-top: 20px; font-size: 13px;"': 'class="pub-pagination"',
    'style="border: 1px; border-left: 2px; border-right: 2px; border-style: solid; border-color: #D6D0AB; padding: 12px 12px 14px 12px; background-color: #F2EFDE; opacity: 0.9; margin-left: 4px"': 'class="wanted-banner"',
    'style="padding: 32px"': 'class="wanted-banner-inner"',
    'style="font: bold 16px Verdana; color: red"': 'class="wanted-banner-title"',
    'style=""': "",
    'style=\'\'': "",
}

CONDITIONAL_REPLACEMENTS = [
    (
        '{if $id eq $r.id}style="border-bottom: 2px solid #800; margin-top: 4px; margin-bottom: 4px"{/if}',
        '{if $id eq $r.id} class="nav-active"{/if}',
    ),
    (
        '{if $catalog[n].online_articles eq 0}style="color: #594C3F"{/if}',
        '{if $catalog[n].online_articles eq 0} class="catalog-link-offline"{/if}',
    ),
    (
        '{if $catalog[n].online_articles eq 0}{/if}',
        '{if $catalog[n].online_articles eq 0} class="catalog-link-offline"{/if}',
    ),
    (
        "{if $comments[n].nickname eq 'newart'}style=\"color: #A41E00\"{/if}",
        "{if $comments[n].nickname eq 'newart'} class=\"guestbook-author-highlight\"{/if}",
    ),
    (
        '{if $other_articles[n].current}',
        '{if $other_articles[n].current}',
    ),
]

H2_ACTIVE = 'style="border-bottom: 2px solid #800; margin-top: 4px; margin-bottom: 4px"'
H2_ACTIVE_CLASS = 'class="nav-active"'


def migrate_template(text: str) -> str:
    for old, new in CONDITIONAL_REPLACEMENTS[:4]:
        text = text.replace(old, new)

    text = text.replace(H2_ACTIVE, H2_ACTIVE_CLASS)

    # books.tpl duplicate style attributes
    text = re.sub(
        r'class="books-title-link"\s+href="([^"]+)"\s+style="color: black; font: bold 14px Times"',
        r'class="books-title-link" href="\1"',
        text,
    )
    text = re.sub(
        r'class="books-action-link"\s+href="([^"]+)"\s+style="color: black; font: bold 14px Times"',
        r'class="books-action-link" href="\1"',
        text,
    )

    for style_attr, class_attr in sorted(STYLE_TO_CLASS.items(), key=lambda x: -len(x[0])):
        if class_attr == "":
            text = text.replace(" " + style_attr, "")
            text = text.replace(style_attr + " ", "")
            text = text.replace(style_attr, "")
        else:
            text = text.replace(style_attr, class_attr)

    # Remove empty class="" left behind
    text = re.sub(r'\sclass=""', "", text)
    return text


def should_process(name: str) -> bool:
    if name in SKIP_FILES:
        return False
    if name.startswith("admin_"):
        return False
    return name.endswith(".tpl")


def main() -> None:
    changed = []
    for path in sorted(TPL_DIR.glob("*.tpl")):
        if not should_process(path.name):
            continue
        original = path.read_text(encoding="utf-8")
        updated = migrate_template(original)
        if updated != original:
            path.write_text(updated, encoding="utf-8")
            remaining = len(re.findall(r"\bstyle\s*=", updated))
            changed.append((path.name, remaining))

    print("Updated templates:")
    for name, remaining in changed:
        print(f"  {name}: {remaining} style= remaining")
    if not changed:
        print("  (none)")


if __name__ == "__main__":
    main()
