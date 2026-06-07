<!DOCTYPE html>
<html><head><title>{$title|strip_tags}</title>
<meta http-equiv=content-type content="text/html; charset=utf-8">
<meta name="google-site-verification" content="3jU_t7yiV6nlzUiQHZMyU5nlowdU8bUn3YH7NVJJ5Kw" />
<link href="/img/style.css" type="text/css" rel="stylesheet">
{include file="metrika.tpl"}
{include file="gtag.tpl"}
</head>
<body class="print-body" onload="javascript:print();">

<!-- <div>{$press.title} #{$issue.title} / {$issue.date} / {$article.title}</div> -->

<main class="print-text-wrap">
<pre class="print-text">{$article.text nofilter}</pre>
</main>
</body>
</html>
