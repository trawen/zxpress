{if $updates_total_pages gt 1}
<nav class="smn-pages{if $updates_pages_top|default:false} smn-pages--top{/if}" data-adaptive-pages="updates" data-total-pages="{$updates_total_pages}" data-current-page="{$tk_page}" data-base-url="{$updates_catalog_url|escape:'html'}" aria-label="{if $lng eq 'eng'}Pages{else}Страницы{/if}">
	{if $tk_page gt 1}
		<a class="smn-pages-prev" href="{if $tk_page-1 gt 1}{$updates_catalog_url}?page={$tk_page-1}{else}{$updates_catalog_url}{/if}"{if !$updates_pages_top|default:false} rel="prev"{/if} aria-label="{if $lng eq 'eng'}Previous page{else}Предыдущая страница{/if}">←</a>
	{else}
		<span class="smn-pages-prev is-disabled" aria-hidden="true">←</span>
	{/if}
	<span class="smn-pages-list">
	{section name=pg loop=$updates_total_pages}
		{assign var=pnum value=$smarty.section.pg.iteration}
		{if $pnum == 1 || $pnum == $updates_total_pages || ($pnum >= $tk_page-2 && $pnum <= $tk_page+2)}
			{if $pnum == $tk_page}
				<b aria-current="page">{$pnum}</b>
			{else}
				<a href="{if $pnum gt 1}{$updates_catalog_url}?page={$pnum}{else}{$updates_catalog_url}{/if}">{$pnum}</a>
			{/if}
		{elseif $pnum == 2 && $tk_page > 4}
			<span class="smn-pages-gap" aria-hidden="true">…</span>
		{elseif $pnum == $updates_total_pages-1 && $tk_page < $updates_total_pages-3}
			<span class="smn-pages-gap" aria-hidden="true">…</span>
		{/if}
	{/section}
	</span>
	{if $tk_page lt $updates_total_pages}
		<a class="smn-pages-next" href="{$updates_catalog_url}?page={$tk_page+1}"{if !$updates_pages_top|default:false} rel="next"{/if} aria-label="{if $lng eq 'eng'}Next page{else}Следующая страница{/if}">→</a>
	{else}
		<span class="smn-pages-next is-disabled" aria-hidden="true">→</span>
	{/if}
</nav>
{/if}
