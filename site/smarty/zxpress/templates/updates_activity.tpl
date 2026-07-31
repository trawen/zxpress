<!DOCTYPE html>
<html lang="{if $lng eq 'eng'}en{else}ru{/if}">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title>{$title|strip_tags} — ZXPRESS</title>
	<style>
		body { font: 15px/1.45 system-ui, sans-serif; margin: 24px; max-width: 960px; color: #1a1a1a; background: #f7f5ef; }
		h1 { font-size: 1.4rem; margin: 0 0 8px; }
		.meta { color: #666; margin-bottom: 16px; }
		.filters a { margin-right: 10px; }
		.filters .on { font-weight: 700; }
		.batch { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 14px 16px; margin: 0 0 12px; }
		.batch h2 { font-size: 1.05rem; margin: 0 0 6px; }
		.batch .sum { color: #444; }
		.batch .when { color: #888; font-size: 13px; margin-top: 4px; }
		.batch details { margin-top: 10px; }
		.batch table { width: 100%; border-collapse: collapse; font-size: 13px; }
		.batch th, .batch td { text-align: left; padding: 4px 6px; border-bottom: 1px solid #eee; vertical-align: top; }
		.pill { display: inline-block; padding: 1px 7px; border-radius: 999px; background: #eee; font-size: 12px; margin-right: 6px; }
		.pill.pub { background: #e6f4ea; }
		.pill.meta { background: #f3e8ff; }
		.pill.hide { background: #fce8e6; }
		.pages a { margin-right: 8px; }
		.warn { padding: 12px; background: #fff3cd; border: 1px solid #ffe69c; border-radius: 6px; }
		code { font-size: 12px; }
	</style>
</head>
<body>
	<h1>{if $lng eq 'eng'}Activity feed (test){else}Лента activity (тест){/if}</h1>
	<p class="meta">
		{if $lng eq 'eng'}
			Reads <code>activity_batch</code> + <code>activity</code>. Not the production /updates page yet.
		{else}
			Читает <code>activity_batch</code> + <code>activity</code>. Это не боевая /updates, а тест новой схемы.
		{/if}
		—
		{if $lng eq 'eng'}<a href="{$url_rus}">rus</a>/<b>eng</b>{else}<b>rus</b>/<a href="{$url_eng}">eng</a>{/if}
	</p>

{if !$activity_ready}
	<div class="warn">Таблицы activity ещё не созданы. Примените <code>db/migration/activity.sql</code>.</div>
{else}
	<p class="filters">
		{if $activity_show_all}
			<a href="{$activity_base_url}{if $activity_domain}?domain={$activity_domain|escape:'url'}{/if}">{if $lng eq 'eng'}Public only{else}Только public{/if}</a>
			<span class="on">{if $lng eq 'eng'}All batches{else}Все batch{/if}</span>
		{else}
			<span class="on">{if $lng eq 'eng'}Public only{else}Только public{/if}</span>
			<a href="{$activity_base_url}?all=1{if $activity_domain}&amp;domain={$activity_domain|escape:'url'}{/if}">{if $lng eq 'eng'}All batches{else}Все batch{/if}</a>
		{/if}
		|
		<a href="{$activity_base_url}{if $activity_show_all}?all=1{/if}"{if $activity_domain eq ''} class="on"{/if}>{if $lng eq 'eng'}all domains{else}все домены{/if}</a>
		{foreach from=$activity_domains item=d}
			<a href="{$activity_base_url}?domain={$d.domain|escape:'url'}{if $activity_show_all}&amp;all=1{/if}"{if $activity_domain eq $d.domain} class="on"{/if}>{$d.domain} ({$d.c})</a>
		{/foreach}
	</p>
	<p class="meta">{if $lng eq 'eng'}Batches{else}Batch-ей{/if}: {$activity_total}</p>

	{if $activity_total_pages gt 1}
	<p class="pages">
		{section name=pg loop=$activity_total_pages}
			{assign var=pnum value=$smarty.section.pg.index+1}
			{if $pnum eq $activity_page}
				<b>{$pnum}</b>
			{else}
				<a href="{$activity_base_url}?page={$pnum}{if $activity_show_all}&amp;all=1{/if}{if $activity_domain}&amp;domain={$activity_domain|escape:'url'}{/if}">{$pnum}</a>
			{/if}
		{/section}
	</p>
	{/if}

	{foreach from=$activity_batches item=b}
	<section class="batch">
		<h2>
			{if $lng eq 'eng'}
				{if $b.title_en}{$b.title_en|escape:'html'}{else}{$b.title_ru|escape:'html'}{/if}
			{else}
				{$b.title_ru|escape:'html'}
			{/if}
		</h2>
		<div class="sum">
			<span class="pill">{$b.domain|escape:'html'}</span>
			{if $b.is_public}<span class="pill pub">public</span>{else}<span class="pill hide">hidden</span>{/if}
			{if $lng eq 'eng'}{$b.summary_en|escape:'html'}{else}{$b.summary_ru|escape:'html'}{/if}
			· {$b.items_count} / public {$b.public_items_count}
		</div>
		<div class="when">{$b.date_label|escape:'html'} · #{$b.id} · {$b.source|escape:'html'}
			{if $b.root_type} · root {$b.root_type}:{$b.root_id}{/if}
		</div>
		{if $b.url_ru || $b.url_en}
			<p><a href="{if $lng eq 'eng' && $b.url_en}{$b.url_en|escape:'html'}{else}{$b.url_ru|escape:'html'}{/if}">{if $lng eq 'eng'}Open{else}Открыть{/if}</a></p>
		{/if}
		{if $b.thumb_url}
			<p><img src="{$b.thumb_url|escape:'html'}" alt="" style="max-width:180px;height:auto"></p>
		{/if}
		<details>
			<summary>{if $lng eq 'eng'}Events ({$b.events|@count}){else}События ({$b.events|@count}){/if}</summary>
			<table>
				<thead>
					<tr>
						<th>ID</th>
						<th>{if $lng eq 'eng'}Type{else}Тип{/if}</th>
						<th>verb/action</th>
						<th>{if $lng eq 'eng'}Title{else}Заголовок{/if}</th>
						<th>scope</th>
					</tr>
				</thead>
				<tbody>
				{foreach from=$b.events item=e}
					<tr>
						<td>{$e.id}</td>
						<td>{$e.object_label|escape:'html'} #{$e.object_id}</td>
						<td><code>{$e.verb|escape:'html'}</code> / <code>{$e.action|escape:'html'}</code></td>
						<td>
							{if $lng eq 'eng'}
								{if $e.title_en}{$e.title_en|escape:'html'}{else}{$e.title_ru|escape:'html'}{/if}
							{else}
								{$e.title_ru|escape:'html'}
							{/if}
							{if $e.url_ru} <a href="{$e.url_ru|escape:'html'}">→</a>{/if}
							{if $e.thumb_url}
								<br><img src="{$e.thumb_url|escape:'html'}" alt="" style="max-width:120px;height:auto;margin-top:4px">
							{/if}
						</td>
						<td>
							{if $e.event_scope eq 'content'}<span class="pill pub">content</span>
							{elseif $e.event_scope eq 'metadata'}<span class="pill meta">metadata</span>
							{else}<span class="pill">{$e.event_scope|escape:'html'}</span>{/if}
							{if !$e.is_public}<span class="pill hide">private</span>{/if}
						</td>
					</tr>
				{/foreach}
				</tbody>
			</table>
		</details>
	</section>
	{foreachelse}
		<p>{if $lng eq 'eng'}No batches yet. Save something in admin.{else}Пока пусто — сохраните что-нибудь в админке.{/if}</p>
	{/foreach}
{/if}
</body>
</html>
