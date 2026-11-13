Tipos de condição
=================

Todos os tipos são subplugins do ``local_enrolconditions`` (pasta ``local/enrolconditions/condition``).

Data (``enrolcond_date``)
   Cumprida a partir de uma data e hora (*From*), até uma data e hora (*Until*) ou entre as duas.

   .. image:: ../img/condition-date.png
      :alt: date
      :width: 520px

Perfil do usuário (``enrolcond_profile``)
   Compara um campo de perfil padrão ou personalizado com um valor: *is equal to*, *contains*, *does not contain*, *starts with*, *ends with*, *is empty* ou *is not empty*. Reavaliada quando o perfil do usuário é atualizado.

   .. image:: ../img/condition-profile.png
      :alt: profile
      :width: 520px

Coorte (``enrolcond_cohort``)
   Cumprida pelos membros de uma coorte disponível no contexto do curso. Reavaliada quando membros entram ou saem de uma coorte. Substitui a restrição por coorte do ``enrol_self``: a atualização a partir de uma versão anterior transforma essa configuração em uma condição de coorte.

   .. image:: ../img/condition-cohort.png
      :alt: cohort
      :width: 520px

Curso concluído (``enrolcond_coursecompleted``)
   Cumprida por quem concluiu outro curso. Reavaliada quando um curso é concluído.

   .. image:: ../img/condition-coursecompleted.png
      :alt: coursecompleted
      :width: 520px

Lista de usernames (``enrolcond_userlist``)
   Usernames digitados na condição, um por linha ou separados por espaço, vírgula ou ponto e vírgula; maiúsculas e minúsculas contam igual. No máximo 5.000 usernames.

   .. image:: ../img/condition-userlist.png
      :alt: userlist
      :width: 520px

Arquivo de usernames (``enrolcond_userfile``)
   Um arquivo CSV ou texto com os usernames na primeira coluna (uma primeira linha ``username`` é tratada como cabeçalho). O arquivo fica guardado no curso, e o professor o substitui para atualizar a lista. No máximo 200.000 usernames. Uma tarefa diária apaga, depois de um dia, os arquivos que nenhuma condição usa mais. Se o arquivo se perder, a condição fica desconhecida.

   .. image:: ../img/condition-userfile.png
      :alt: userfile
      :width: 520px

Lista de consulta (``enrolcond_sqlquery``)
   Uma das listas definidas pelo administrador (veja :doc:`configuration`). O professor escolhe a lista pelo nome e nunca vê a consulta. O resultado fica em cache; uma consulta com erro é desconhecida. Dentro de uma transação de banco aberta a consulta não roda: sem resultado em cache, a condição fica desconhecida até ser avaliada fora de uma, por exemplo no login ou na reavaliação periódica.

   .. image:: ../img/condition-sqlquery.png
      :alt: sqlquery
      :width: 520px

Condições de outros plugins
---------------------------

Qualquer plugin pode fornecer condições pelo hook ``\local_enrolconditions\hook\collect_conditions``, com classes que estendem ``\local_enrolconditions\condition\base``. Veja o README do ``local_enrolconditions``.
