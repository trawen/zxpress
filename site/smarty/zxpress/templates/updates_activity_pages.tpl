{if $activity_total_pages gt 1}
<nav class="smn-pages{if $updates_pages_top|default:false} smn-pages--top{/if}" data-adaptive-pages="activity" data-total-pages="{$activity_total_pages}" data-current-page="{$activity_page}" data-base-url="{$activity_pages_base_url|escape:'html'}" aria-label="{if $lng eq 'eng'}Pages{else}Страницы{/if}">
	{if $activity_page gt 1}
		{if $activity_page-1 gt 1}
		<a class="smn-pages-prev" href="{$activity_pages_base_url|escape:'html'}{$activity_page_join|escape:'html'}page={$activity_page-1}"{if !$updates_pages_top|default:false} rel="prev"{/if} aria-label="{if $lng eq 'eng'}Previous page{else}Предыдущая страница{/if}">←</a>
		{else}
		<a class="smn-pages-prev" href="{$activity_pages_base_url|escape:'html'}"{if !$updates_pages_top|default:false} rel="prev"{/if} aria-label="{if $lng eq 'eng'}Previous page{else}Предыдущая страница{/if}">←</a>
		{/if}
	{else}
		<span class="smn-pages-prev is-disabled" aria-hidden="true">←</span>
	{/if}
	<span class="smn-pages-list">
	{section name=pg loop=$activity_total_pages}
		{assign var=pnum value=$smarty.section.pg.iteration}
		{if $pnum == 1 || $pnum == $activity_total_pages || ($pnum >= $activity_page-2 && $pnum <= $activity_page+2)}
			{if $pnum == $activity_page}
				<b aria-current="page">{$pnum}</b>
			{elseif $pnum gt 1}
				<a href="{$activity_pages_base_url|escape:'html'}{$activity_page_join|escape:'html'}page={$pnum}">{$pnum}</a>
			{else}
				<a href="{$activity_pages_base_url|escape:'html'}">{$pnum}</a>
			{/if}
		{elseif $pnum == 2 && $activity_page > 4}
			<span class="smn-pages-gap" aria-hidden="true">…</span>
		{elseif $pnum == $activity_total_pages-1 && $activity_page < $activity_total_pages-3}
			<span class="smn-pages-gap" aria-hidden="true">…</span>
		{/if}
	{/section}
	</span>
	{if $activity_page lt $activity_total_pages}
		<a class="smn-pages-next" href="{$activity_pages_base_url|escape:'html'}{$activity_page_join|escape:'html'}page={$activity_page+1}"{if !$updates_pages_top|default:false} rel="next"{/if} aria-label="{if $lng eq 'eng'}Next page{else}Следующая страница{/if}">→</a>
	{else}
		<span class="smn-pages-next is-disabled" aria-hidden="true">→</span>
	{/if}
</nav>
{/if}
