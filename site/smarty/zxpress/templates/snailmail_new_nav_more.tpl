<div class="smn-nav-secondary">
{if $lng eq 'eng'}
	<a href="{$authors_catalog_url|default:'/en/authors'}"{if $smn_nav_authors_active|default:false} class="is-active"{/if}>Authors of articles, letters and publications</a>
	<a href="{if $lng eq 'eng'}/en{else}/ru{/if}/map"{if $smn_nav_map_active|default:false} class="is-active"{/if}>Publications map</a>
	<a href="{$host}">Library</a>
	{if $login eq 1}
	<form method="post" action="{$host}logout.php" class="smn-nav-more-logout">
		<input type="hidden" name="csrf_token" value="{$csrf_token}">
		<button type="submit">Log out ({$username})</button>
	</form>
	{/if}
	<a href="{$host}news{$sl|default:'?lng=eng'}">News</a>
	<a href="{if $lng eq 'eng'}/en{else}/ru{/if}/updates-new"{if $smn_nav_updates_active|default:false} class="is-active"{/if}>Updates</a>
	<a href="{if $lng eq 'eng'}/en{else}/ru{/if}/categories"{if $smn_nav_categories_active|default:false} class="is-active"{/if}>Categories</a>
	<a href="{if $lng eq 'eng'}/en{else}/ru{/if}/gallery-new"{if $smn_nav_gallery_active|default:false} class="is-active"{/if}>Gallery</a>
	<a href="{$host}chronology.php{$sl|default:'?lng=eng'}">Hronology</a>
	<a href="{$host}stats.php{$sl|default:'?lng=eng'}">Stats</a>
	<a href="{if $lng eq 'eng'}/en{else}/ru{/if}/guestbook-new"{if $smn_nav_guestbook_active|default:false} class="is-active"{/if}>Guestbook</a>
	<a href="{$host}whois.php{$sl|default:'?lng=eng'}">?</a>
{else}
	<a href="{$authors_catalog_url|default:'/ru/authors'}"{if $smn_nav_authors_active|default:false} class="is-active"{/if}>Авторы статей, писем и публикаций</a>
	<a href="{if $lng eq 'eng'}/en{else}/ru{/if}/map"{if $smn_nav_map_active|default:false} class="is-active"{/if}>Карта изданий</a>
	{if $login eq 1}
	<form method="post" action="{$host}logout.php" class="smn-nav-more-logout">
		<input type="hidden" name="csrf_token" value="{$csrf_token}">
		<button type="submit">Выйти ({$username})</button>
	</form>
	{/if}
	<a href="{if $lng eq 'eng'}/en{else}/ru{/if}/updates-new"{if $smn_nav_updates_active|default:false} class="is-active"{/if}>Обновления</a>
	<a href="{if $lng eq 'eng'}/en{else}/ru{/if}/categories"{if $smn_nav_categories_active|default:false} class="is-active"{/if}>Категории</a>
	<a href="{if $lng eq 'eng'}/en{else}/ru{/if}/gallery-new"{if $smn_nav_gallery_active|default:false} class="is-active"{/if}>Галерея</a>
	<a href="{$host}chronology.php">Хронология</a>
	<a href="{$host}stats.php">Статистика</a>
	<a href="{if $lng eq 'eng'}/en{else}/ru{/if}/guestbook-new"{if $smn_nav_guestbook_active|default:false} class="is-active"{/if}>Гостевая</a>
	<a href="{$host}whois.php">?</a>
{/if}
</div>
