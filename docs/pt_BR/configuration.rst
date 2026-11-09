Configuração
============

Configurações do plugin
-----------------------

*Administração do site > Plugins > Inscrições > Conditional self enrolment* traz as configurações do ``enrol_self`` e mais:

When the conditions are no longer met
   Ação padrão das novas instâncias: *Nothing (only record it)* (só registrar), *Suspend the enrolment* (suspender, padrão) ou *Remove the enrolment* (remover).

Reactivate when the conditions are met again
   Padrão das novas instâncias para a reativação. Desligado por padrão.

When the user logs in / Periodically, in the scheduled task / When something the conditions depend on changes
   Modos de reavaliação padrão das novas instâncias. Todos desligados por padrão: as novas instâncias só avaliam as condições quando o usuário se inscreve.

Enrolments per periodic check
   Máximo de inscrições reavaliadas a cada execução da tarefa agendada (padrão 500). A execução seguinte continua de onde a anterior parou.

Cursos novos não recebem uma instância deste método por padrão (*Adicionar instância a novos cursos* fica desligado).

Gerenciando os tipos de condição
--------------------------------

*Administração do site > Plugins > Inscrições > Enrolment conditions > Manage conditions* lista os tipos de condição instalados. Um tipo desabilitado deixa de ser oferecido aos professores; as instâncias que já o usam recusam novas autoinscrições, porque a condição não pode mais ser avaliada, e nunca suspendem ninguém por causa dele.

Listas de consulta
------------------

A condição *List from a query* só oferece as listas que um administrador definiu na página de configurações dela (*Manage conditions* e depois *Configurações* de *List from a query*):

Number of lists
   Quantas listas podem ser definidas (padrão 3, no máximo 20).

Name e Query
   Para cada lista. O nome aparece para os professores e para os usuários que não cumprem a condição; nome vazio desabilita a lista. A consulta é um único ``SELECT`` (ou ``WITH ... SELECT``) que devolve uma coluna chamada ``username``. Use ``{tabela}`` nos nomes de tabela; ``:courseid`` é o curso do método de inscrição.

Keep results for
   Por quanto tempo os usernames devolvidos são reaproveitados antes de a consulta rodar de novo (padrão 5 minutos; 0 roda a cada avaliação).

Ao salvar as configurações, cada consulta é verificada e executada uma vez. São recusadas consultas com ``;``, comentários, comandos que alteram dados, ``INTO`` ou funções de espera e de leitura de arquivos do servidor.

.. warning::
   As consultas rodam com as permissões de banco do Moodle e podem ler qualquer tabela, inclusive senhas e dados pessoais. As verificações pegam erros comuns, não um administrador mal-intencionado. Revise cada consulta como se fosse código.

Exemplo, os usuários de uma instituição:

.. code-block:: text

   SELECT username FROM {user} WHERE institution = 'IFRN' AND deleted = 0
