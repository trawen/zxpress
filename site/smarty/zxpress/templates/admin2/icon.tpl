{if isset($class) && $class}{assign var=icon_class value=$class}{else}{assign var=icon_class value='admin-icon'}{/if}
<svg class="{$icon_class}" width="16" height="16" aria-hidden="true"><use href="#icon-{$name}"></use></svg>
