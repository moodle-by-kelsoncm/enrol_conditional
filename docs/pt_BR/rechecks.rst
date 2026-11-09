Reavaliação e ações
===================

Quando as condições são avaliadas
---------------------------------

*Check the conditions*, em cada instância:

When the user enrols (always)
   A autoinscrição e o web service sempre avaliam as condições.

When the user logs in
   As inscrições do usuário nas instâncias com este modo são reavaliadas a cada login.

Periodically, in the scheduled task
   A tarefa *Re-check the conditions of conditional self enrolments* reavalia as inscrições em lotes (*Enrolments per periodic check*) e continua de onde a execução anterior parou.

When something the conditions depend on changes
   As inscrições são reavaliadas quando ocorre um evento observado pelas condições: perfil atualizado (perfil), membro adicionado ou removido de coorte (coorte), curso concluído (curso concluído). As condições que dependem só do tempo ou de listas (data, usernames, consultas) não têm evento: combine-as com a reavaliação no login ou periódica.

O que acontece quando as condições deixam de ser cumpridas
----------------------------------------------------------

*When the conditions are no longer met*:

Nothing (only record it)
   O plugin registra o momento e dispara o evento *Enrolment conditions no longer met* uma vez, quando o usuário deixa de cumprir as condições, e não a cada avaliação.

Suspend the enrolment (padrão)
   A inscrição é suspensa e o evento *User enrolment suspended by conditions* é disparado.

Remove the enrolment
   O evento *Enrolment conditions no longer met* é disparado e o usuário é desinscrito. Os dados do usuário no curso seguem as regras do site para a desinscrição.

*Reactivate when the conditions are met again*: as inscrições que este plugin suspendeu são reativadas quando o usuário volta a cumprir as condições, com o evento *User enrolment reactivated by conditions*. Remover todas as condições conta como cumpri-las.

Quais suspensões são reativadas
-------------------------------

O plugin só reativa uma inscrição que **ele mesmo** suspendeu e que **ninguém alterou depois**. Ele guarda um registro próprio de cada suspensão (tabela ``enrol_conditional_state``). Se um professor, um administrador ou outro plugin suspender, reativar ou editar a inscrição, o registro é encerrado e o plugin não mexe mais nela. Uma suspensão manual nunca é desfeita pelas condições.

Resultados desconhecidos (condição que não pode ser avaliada) nunca suspendem, removem nem reativam.
