<div class="smn-nav-secondary">
{if $lng eq 'eng'}
	<a href="{if $lng eq 'eng'}/en{else}/ru{/if}/categories"{if $smn_nav_categories_active|default:false} class="is-active"{/if}>Article categories</a>
	<a href="{if $lng eq 'eng'}/en{else}/ru{/if}/map"{if $smn_nav_map_active|default:false} class="is-active"{/if}>ZX-press map</a>
	<a href="{$authors_catalog_url|default:'/en/authors'}"{if $smn_nav_authors_active|default:false} class="is-active"{/if}>Authors of articles, letters and publications</a>
	<a href="{$host}">Library</a>
	{if $login eq 1}
	<form method="post" action="{$host}logout.php" class="smn-nav-more-logout">
		<input type="hidden" name="csrf_token" value="{$csrf_token}">
		<button type="submit">Log out ({$username})</button>
	</form>
	{/if}
	<a href="{$host}news{$sl|default:'?lng=eng'}">News</a>
	<a href="{if $lng eq 'eng'}/en{else}/ru{/if}/updates"{if $smn_nav_updates_active|default:false} class="is-active"{/if}>What's new</a>
	<a href="{if $lng eq 'eng'}/en{else}/ru{/if}/gallery"{if $smn_nav_gallery_active|default:false} class="is-active"{/if}>Gallery</a>
	<a href="{if $lng eq 'eng'}/en{else}/ru{/if}/guestbook"{if $smn_nav_guestbook_active|default:false} class="is-active"{/if}>Guestbook</a>
{else}
	<a href="{if $lng eq 'eng'}/en{else}/ru{/if}/categories"{if $smn_nav_categories_active|default:false} class="is-active"{/if}>Категории статей</a>
	<a href="{if $lng eq 'eng'}/en{else}/ru{/if}/map"{if $smn_nav_map_active|default:false} class="is-active"{/if}>Карта ZX-прессы</a>
	<a href="{$authors_catalog_url|default:'/ru/authors'}"{if $smn_nav_authors_active|default:false} class="is-active"{/if}>Авторы статей, писем и публикаций</a>
	{if $login eq 1}
	<form method="post" action="{$host}logout.php" class="smn-nav-more-logout">
		<input type="hidden" name="csrf_token" value="{$csrf_token}">
		<button type="submit">Выйти ({$username})</button>
	</form>
	{/if}
	<a href="{if $lng eq 'eng'}/en{else}/ru{/if}/updates"{if $smn_nav_updates_active|default:false} class="is-active"{/if}>Что нового</a>
	<a href="{if $lng eq 'eng'}/en{else}/ru{/if}/gallery"{if $smn_nav_gallery_active|default:false} class="is-active"{/if}>Галерея</a>
	<a href="{if $lng eq 'eng'}/en{else}/ru{/if}/guestbook"{if $smn_nav_guestbook_active|default:false} class="is-active"{/if}>Гостевая</a>
{/if}
</div>
