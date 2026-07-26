{if $gallery_total_pages gt 1}
<nav class="smn-pages{if $gallery_pages_top|default:false} smn-pages--top{/if}" aria-label="{if $lng eq 'eng'}Pages{else}Страницы{/if}">
	{if $gallery_prev_page}
		<a class="smn-pages-prev" href="{$gallery_catalog_url}?page={$gallery_prev_page}"{if !$gallery_pages_top|default:false} rel="prev"{/if} aria-label="{if $lng eq 'eng'}Previous page{else}Предыдущая страница{/if}">←</a>
	{else}
		<span class="smn-pages-prev is-disabled" aria-hidden="true">←</span>
	{/if}
	{section name=pg loop=$gallery_total_pages}
		{assign var=pnum value=$smarty.section.pg.iteration}
		{if $pnum == $gallery_page}
			<b aria-current="page">{$pnum}</b>
		{else}
			<a href="{$gallery_catalog_url}?page={$pnum}">{$pnum}</a>
		{/if}
	{/section}
	{if $gallery_next_page}
		<a class="smn-pages-next" href="{$gallery_catalog_url}?page={$gallery_next_page}"{if !$gallery_pages_top|default:false} rel="next"{/if} aria-label="{if $lng eq 'eng'}Next page{else}Следующая страница{/if}">→</a>
	{else}
		<span class="smn-pages-next is-disabled" aria-hidden="true">→</span>
	{/if}
</nav>
{/if}
