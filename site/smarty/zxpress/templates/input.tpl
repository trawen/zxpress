{include file="top.tpl"}
<TABLE cellSpacing=0 cellPadding=0 align="center">
<TBODY>
<TR>
<TD>

<center><H1>Вход в кладовые ;)</H1></center><br><br>

<br><br>
{if $login eq 1}
{if $logout_notice|default:0}<p class="msg-success"><strong>Вы вышли из системы.</strong></p>{/if}
{if $session_timeout_notice|default:0}<p class="msg-warn"><strong>Сессия истекла из‑за неактивности. Войдите снова.</strong></p>{/if}
<div class="input-user-badge-pos">
<div class="input-user-label">Вы <span class="msg-error-inline">{$username}</span></div>
</div>
<p class="u-mt-1em">
<form method="post" action="logout.php" class="u-inline">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<button type="submit" class="btn-auth">Выйти</button>
</form>
</p>
{else}
<center>
{if $login_error_no_rights|default:0}
<p class="msg-warn-block"><strong>Учётная запись найдена, но нет прав администратора (в БД users.level должно быть 1 или NULL).</strong></p>
{elseif $login_error|default:0}
<p class="msg-warn-block"><strong>Неверный логин или пароль.</strong></p>
{/if}
<form method='POST'>
<input type="hidden" name="csrf_token" value="{$csrf_token}">

<input type="text" name="username" size="8" class="input-auth" maxlength="32"> 
<input type="password" name="password" size="8" class="input-auth" maxlength="256" autocomplete="current-password">
<button type="submit" name="auth_submit" value="1" class="btn-auth">войти</button>



</form>
</center>
{/if}


<BR>
</TD></TR></TBODY></TABLE>
{include file="right.tpl"}
{include file="footer.tpl"}