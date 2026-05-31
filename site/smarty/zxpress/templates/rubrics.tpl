
{include file="top.tpl"}

{function name=menu level=0}     
  <ul{if $level eq 0} class="tree"{/if}>
  
  {foreach from=$data item=r}
    
    {if is_array($r.tree)}

      <li class="{if $r.last}last{/if} {if $level eq 0}first{/if}">
      	<div>
      	<a href="rubrics.php?id={$r.id}&r={$r_id}#articles" {if $id eq $r.id} class="nav-active"{/if}>{$r.name_plain}</a> 
      	{if $r.articles}<span class="tree-item-count">{$r.articles}</span>{/if}
      	</div>

      	{menu data=$r.tree level=$level+1}

      </li>



    {else}
      
      <li class="{if $r.last}last{/if} {if $level eq 0}first{/if}">
      	<a href="rubrics.php?id={$r.id}&r={$r_id}#articles" {if $id eq $r.id} class="nav-active"{/if}>{$r.name_plain}</a>
      	{if $r.articles}<span class="tree-item-count">{$r.articles}</span>{/if}
      </li>

    {/if}

  {/foreach}

  </ul>
{/function}



{menu data=$rubrics}


{if $id}
<br>
<a name="articles">
<b><h1>{$h1}</h1></b>
</a>
{/if}


{foreach from=$rubrics_articles item=ra}

{if $ra.show}
<br>
<div>
<a class="link-issue" href="{$host}issue.php?id={$ra.id_press}#{$ra.id_issue}">{$ra.press_name_plain} #{$ra.nm_issue}</a> 
<br>
</div>
{if $ra.date}<div class="rubrics-date">{$ra.date}</div>{/if}
{/if}

<div class="type-article-link">
<a href="{$host}article.php?id={$ra.id_article}">{$ra.title_list nofilter}</a>
</div>

{/foreach}


{if $id}

<p onclick="scroll(0,0)">← к меню</p>

{/if}



{include file="right.tpl"}
{include file="footer.tpl"}

