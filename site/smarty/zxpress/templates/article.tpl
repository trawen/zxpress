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
                <a class="title" style="font: 13pt Georgia; color: #800;"
                    href="{$host}issue.php?id={$press[5]}{$dl}#{$issue.title}">{$article.name_plain}</a>
            {else}
                <a class="title" style="font: 13pt Georgia; color: #800;"
                    href="{$host}issue.php?id={$press[5]}{$dl}#{$issue.title}">{$press.title_plain}
                    #{$issue.title}</a>
            {/if}

            {if $issue.date neq "01 января 1970"}
            <div class="date">{$issue.date}</div>{/if}
        </td>
        <td align=right>


            <div style="height: 20px; float: right; padding-right: 10px">
                <img src="/img/monitor.png" width=20 onclick="ToggleColors();" style="cursor: pointer">
            </div>

            <div style="height: 20px; float: right; padding-right: 10px">
                <a rel="nofollow" href="pure-text.php?id={$id}"><img src="/img/dws.png" height=19></a>
            </div>


            <div style="height: 20px; float: right; padding-right: 10px">
                <a rel="nofollow" href="print.php?id={$id}" target="_blank"><img src="/img/print.png" width=20></a>
            </div>

            {foreach from=$tags item=t}
                <div style="height: 20px; float: right; padding-right: 16px">
                    <div
                        style="height: 20px; width: 13px; background: url('/img/tag1.png') 100% 100% no-repeat; float: left;">
                    </div>
                    <div style="height: 20px; background: url('img/tag2.png') 100% 100% repeat-x; float: left;">
                        &nbsp; <a href="tag.php?id={$t.id}">{$t.tag_name}</a>
                        &nbsp;
                    </div>
                    <div style="height: 20px; width: 5px; background: url('img/tag3.png') 100% 100%; float: left;">
                    </div>
                </div>
            {/foreach}

        </td>
    </tr>
</table>


{if $lng eq 'eng'}
    <div>
        <div>
            <h1 style="">{$article.title_eng_html nofilter}</h1>
        </div>
        <div style="justify-self: center;"><img src="{$host}screens/1/{$screens.id}.{$screens.format}" width="128"
                title="{$article.title_eng_plain_meta}" alt="{$article.title_eng_plain_meta}"></div>
    </div>
{else}
    <div>
        <div>
            <h1 style="">{$article.title_html nofilter}</h1>
        </div>
        <div style="justify-self: center;"><img src="{$host}screens/1/{$screens.id}.{$screens.format}" width="128"
                title="{$article.title_plain_meta}" alt="{$article.title_plain_meta}"></div>
    </div>
{/if}

{if $breadcrumbs}
    <hr>
    {foreach from=$breadcrumbs item=b name=b}

        {if $smarty.foreach.b.iteration gt 1} → {/if}
        <a href="{$host}menu/{if $b.parent}{$breadcrumbs[0].id}/{/if}{$b.id}" style="color:black">{$b.name_plain}</a>
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
        <div style="font: bold 13pt Georgia">Other articles:</div>
        <br>
        <table style="width: 600;">
            {section name=n loop=$other_articles}
                <tr>
                    {if $other_articles[n].current}
                        <td>
                            <h2 style="border-bottom: 2px solid #800; margin-top: 4px; margin-bottom: 4px">
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
        <div style="font: bold 13pt Georgia">Другие статьи номера:</div>
        <br>
        <table style="width: 600;">
            {section name=n loop=$other_articles}
                <tr>
                    {if $other_articles[n].current}
                        <td>
                            <h2 style="border-bottom: 2px solid #800; margin-top: 4px; margin-bottom: 4px">{$other_articles[n].title_html nofilter}
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
