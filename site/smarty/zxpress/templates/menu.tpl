{include file="top.tpl"}

<script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
<script type="text/javascript">var CSRF_TOKEN = '{$csrf_token}';</script>

{function name=menu level=0}     
  <ul{if $level eq 0} class="tree"{/if}>
  
  {foreach from=$data item=r}
    
    {if is_array($r.tree)}

      <li class="{if $r.last}last{/if} {if $level eq 0}first{/if}">
      	
      	<a href="{$host}menu/{$r_id}/{$r.id}#articles" {if $id eq $r.id}style="border-bottom: 2px solid #800; margin-top: 4px; margin-bottom: 4px"{/if}>{$r.name_plain}</a> 
      	{if $r.articles}<span style="font: normal 12px Arial; color: #aaa">{$r.articles}</span>{/if}
        {if $login}
        <input type="button" onclick="add_tree({$r.id})" value="+" style="height: 22px">
      	{/if}
      	{menu data=$r.tree level=$level+1}

      </li>



    {else}
      
      <li class="{if $r.last}last{/if} {if $level eq 0}first{/if}">
      	<a href="{$host}menu/{$r_id}/{$r.id}#articles" {if $id eq $r.id}style="border-bottom: 2px solid #800; margin-top: 4px; margin-bottom: 4px"{/if}>{$r.name_plain}</a>
      	{if $r.articles}<span style="font: normal 12px Arial; color: #aaa">{$r.articles}</span>{/if}
        {if $login}
        <input type="button" onclick="add_tree({$r.id})" value="+" style="height: 22px">
        {/if}
      </li>

    {/if}

  {/foreach}

  </ul>
{/function}

{menu data=$rubrics}

{if $login}
<br><br>
<input type="button" onclick="add_tree()" value=" + ">

{/if}

{if $breadcrumbs}
<a name="articles">

<hr>

{foreach from=$breadcrumbs item=b name=b}

{if $smarty.foreach.b.iteration gt 1} → {/if}

<a href="{$host}menu/{if $b.parent}{$breadcrumbs[0].id}/{/if}{$b.id}">{$b.name_plain}</a>

{/foreach}
<hr>
</a>
{/if}



{if $menu.description}
<p class="menu">{$menu.description_plain}</p>
{/if}


{foreach from=$rubrics_articles item=ra}

{if $ra.show}
<br>
<div class="name">
{$ra.press_name_plain} #{$ra.nm_issue}
</div>
{if $ra.date}<div class="date">{$ra.date}</div>{/if}
{/if}

<h3 class="title">
<a href="{$host}article.php?id={$ra.id_article}">{$ra.title_list nofilter}</a>
<!-- {friendly_url string="{$ra.title}"} -->
</h3>

{/foreach}


{if $id}

<hr>

<div class="back">← к меню</div>

{/if}



{literal}
<script>

function add_tree(id = 0) {

  var name = prompt("Название", "");

  if (name) {

      $.post(
      "/add_tree.php",
      {
        
        id: id,
        name: name,
        csrf_token: CSRF_TOKEN
        
      },
      onAjaxSuccess
    );

  }

  function onAjaxSuccess(data) {

      
      if (parseInt(data) >= 1) {

        document.location.href = 'http://zxpress.ru/menu/'+data;

      }
      else {

        location.reload();

      }

  }

}

$(document).ready(function() {
	
  $('.back').on('click', function() {

    var p = $(".content").position();
    scroll(0, p.top );

  });

});

</script>
{/literal}




{include file="right.tpl"}
{include file="footer.tpl"}

