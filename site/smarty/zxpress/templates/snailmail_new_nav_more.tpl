<div class="smn-nav-secondary">
{if $lng eq 'eng'}
	<a href="{$authors_catalog_url|default:'/en/authors'}"{if $smn_nav_authors_active|default:false} class="is-active"{/if}>Authors of articles, letters and publications</a>
	<a href="{$host}">Library</a>
	{if $login eq 1}
	<form method="post" action="{$host}logout.php" class="smn-nav-more-logout">
		<input type="hidden" name="csrf_token" value="{$csrf_token}">
		<button type="submit">Log out ({$username})</button>
	</form>
	{/if}
	<a href="{$host}news{$sl|default:'?lng=eng'}">News</a>
	<a href="{$host}updates.php{$sl|default:'?lng=eng'}">Updates</a>
	<a href="{$host}gallery.php{$sl|default:'?lng=eng'}">Gallery</a>
	<a href="{$host}chronology.php{$sl|default:'?lng=eng'}">Hronology</a>
	<a href="{$host}stats.php{$sl|default:'?lng=eng'}">Stats</a>
	<a href="{$host}guestbook.php{$sl|default:'?lng=eng'}">Guestbook</a>
	<a href="{$host}whois.php{$sl|default:'?lng=eng'}">?</a>
{else}
	<a href="{$authors_catalog_url|default:'/ru/authors'}"{if $smn_nav_authors_active|default:false} class="is-active"{/if}>Авторы статей, писем и публикаций</a>
	{if $login eq 1}
	<form method="post" action="{$host}logout.php" class="smn-nav-more-logout">
		<input type="hidden" name="csrf_token" value="{$csrf_token}">
		<button type="submit">Выйти ({$username})</button>
	</form>
	{/if}
	<a href="https://t.me/zxpress" target="_blank" rel="noopener">Мы в телеграме</a>
	<a href="{$host}updates.php">Обновления</a>
	<a href="{$host}gallery.php">Галерея</a>
	<a href="{$host}chronology.php">Хронология</a>
	<a href="{$host}stats.php">Статистика</a>
	<a href="{$host}guestbook.php">Гостевая</a>
	<a href="{$host}whois.php">?</a>
{/if}
</div>
