Backup, restauração e privacidade
=================================

Backup e restauração
--------------------

* As condições fazem parte do método de inscrição e vão em todo backup do curso.
* Os arquivos das condições *File of usernames* vão no backup e são restaurados no novo curso.
* Quando os usuários são incluídos, os registros de suspensão em aberto também vão, para que o curso restaurado ainda possa reativar as inscrições que o plugin suspendeu.
* Restauradas em outro site, as condições *Cohort* e *Course completed* perdem a coorte ou o curso: ficam apontando para nada e não são cumpridas até um professor editá-las. No mesmo site, são mantidas.
* A condição *List from a query* guarda o número da lista; em outro site, usa a lista de mesmo número daquele site, se houver.
* Uma árvore de condições que deixou de ser válida é mantida como está e continua bloqueando a autoinscrição até ser editada.

Privacidade
-----------

* O ``enrol_conditional`` guarda, para cada suspensão feita pelo plugin, a inscrição, a ação, o motivo e as datas. Esses registros são exportados e excluídos com o curso ou com o usuário, pela Privacy API.
* O ``enrolcond_userfile`` encontra os usuários listados nos arquivos de usernames: a solicitação de dados deles exporta os nomes dos arquivos, e a exclusão remove a linha deles dos arquivos.
* Os demais tipos de condição não guardam dados pessoais.
