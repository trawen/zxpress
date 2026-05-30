{include file="top.tpl"}

<meta property="og:description" content="{$article.title_plain_meta}" />
<meta property="og:title" content="{$article.title_plain_meta}" />
<meta property="og:site_name" content="ZXPRESS" />
<meta property="og:type" content="article" />
<meta property="og:image" content="{$host}screens/1/{$screens.id}.{$screens.format}" />

<meta name="twitter:description" content="{$article.title_plain_meta}">
<meta name="twitter:title" content="{$article.title_plain_meta}">
<meta name="twitter:site" content="@zxpressru">
<meta name="twitter:creator" content="@zxpressru">
<meta name="twitter:image" content="{$host}screens/1/{$screens.id}.{$screens.format}">
<meta name="twitter:image:alt" content="{$article.title_plain_meta}">
<meta name="twitter:card" content="summary_large_image">

<table width=100% cellspacing=0 cellpadding=0 border=0>
    <tr>
        <td>

            {if $lng eq 'eng'}
                <a class="title link-issue"
                    href="{$host}issue.php?id={$press[5]}{$dl}#{$issue.title}">{$article.name_plain}</a>
            {else}
                <a class="title link-issue"
                    href="{$host}issue.php?id={$press[5]}{$dl}#{$issue.title}">{$press.title_plain}
                    #{$issue.title}</a>
            {/if}

            {if $issue.date neq "01 января 1970"}
            <div class="date">{$issue.date}</div>{/if}
        </td>
        <td align=right class="article-toolbar-cell">


            <div class="article-toolbar-item">
                <img src="/img/monitor.png" width=20 onclick="ToggleColors();" class="u-clickable">
            </div>

            <div class="article-toolbar-item">
                <a rel="nofollow" href="pure-text.php?id={$id}"><img src="/img/dws.png" height=19></a>
            </div>


            <div class="article-toolbar-item">
                <a rel="nofollow" href="print.php?id={$id}" target="_blank"><img src="/img/print.png" width=20></a>
            </div>

            {foreach from=$tags item=t}
                <div class="article-toolbar-item article-toolbar-item--tags">
                    <div
                        class="article-tag-left">
                    </div>
                    <div class="article-tag-mid">
                        &nbsp; <a href="tag.php?id={$t.id}">{$t.tag_name}</a>
                        &nbsp;
                    </div>
                    <div class="article-tag-right">
                    </div>
                </div>
            {/foreach}

        </td>
    </tr>
</table>


{if $lng eq 'eng'}
    <div>
        <div>
            <h1>{$article.title_eng_html nofilter}</h1>
        </div>
        <div class="article-screen-wrap"><img src="{$host}screens/1/{$screens.id}.{$screens.format}" width="128"
                title="{$article.title_eng_plain_meta}" alt="{$article.title_eng_plain_meta}"></div>
    </div>
{else}
    <div>
        <div>
            <h1>{$article.title_html nofilter}</h1>
        </div>
        <div class="article-screen-wrap"><img src="{$host}screens/1/{$screens.id}.{$screens.format}" width="128"
                title="{$article.title_plain_meta}" alt="{$article.title_plain_meta}"></div>
    </div>
{/if}

{if $breadcrumbs}
    <hr>
    {foreach from=$breadcrumbs item=b name=b}

        {if $smarty.foreach.b.iteration gt 1} → {/if}
        <a href="{$host}menu/{if $b.parent}{$breadcrumbs[0].id}/{/if}{$b.id}" class="u-link-black">{$b.name_plain}</a>
    {/foreach}
    <hr>
    <br>
{/if}

<div id="id-575149-2"></div>

<!-- <div id="yandex_rtb_R-A-575149-1"></div> -->

{if $article.temp and $roskom neq "fuck"}
    <center>
        <!-- <img src="http://lurkmore.so/images/0/0a/RKN_ANUS.jpg" width="600"> -->
        <br><br>
        <!-- <img src="http://static5.cmtt.ru/tj_paper/comments/9-5366/67910553e221f4ba130.jpg" width="600"> -->
        Искомый адрес внесен в реестр на основаниях, предусмотренных статьей
        15.1 Федерального закона от 27 июля 2006 года No 149-ФЗ
    </center>
{else}
    {* Trusted legacy body from disk (RGB spans etc.); must match chapter.tpl — escape_html would show raw tags *}
    <pre id="text">{$article.text nofilter}</pre>
{/if}
<br>
<div class="ya-share2" data-services="vkontakte,facebook,odnoklassniki" data-counter=""
    {if $screen.id}data-image="{$host}screens/1/{$screen.id}.png" {/if}></div>


{if $other_articles}
    <hr>
    <br>
    {if $lng eq 'eng'}
        <div class="article-related-heading">Other articles:</div>
        <br>
        <table class="article-related-table">
            {section name=n loop=$other_articles}
                <tr>
                    {if $other_articles[n].current}
                        <td>
                            <h2 class="nav-active">
                                {$other_articles[n].title_eng_html nofilter}</h2>
                        </td>
                    {else}
                        <td>
                            <h2>
                                <a href="article.php?id={$other_articles[n].id}{$dl}"> {$other_articles[n].title_eng_html nofilter}</a>
                            </h2>
                        </td>
                    {/if}
                </tr>
            {/section}
            {if $sape1}
                <tr>
                    <td>{$sape1 nofilter}</td>
                </tr>
            {/if}
        </table>
    {else}
        <div class="article-related-heading">Другие статьи номера:</div>
        <br>
        <table class="article-related-table">
            {section name=n loop=$other_articles}
                <tr>
                    {if $other_articles[n].current}
                        <td>
                            <h2 class="nav-active">{$other_articles[n].title_html nofilter}
                            </h2>
                        </td>
                    {else}
                        <td>
                            <h2>
                                <a href="article.php?id={$other_articles[n].id}{$dl}"> {$other_articles[n].title_html nofilter}</a>
                            </h2>
                        </td>
                    {/if}
                </tr>
            {/section}
            {if $sape1}
                <tr>
                    <td>{$sape1 nofilter}</td>
                </tr>
            {/if}
        </table>
    {/if}
{/if}


{include file="right.tpl"}
{include file="footer.tpl"}
