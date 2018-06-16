# php-mysql-remote-dumper

## feito para *Windows*

### Como utilizar
1 - Baixe o repositório
2 - Crie o banco de dados local e faça as configurações necessárias no cabeçalho da index
3 - Configure uma tarefa que execute pelo menos de hora em hora no servidor local
4 - Adicione os servidores remotos ao banco de dados
5 - Verifique regularmente os backups gerados se estão plenamente funcionais

### Funcionamento
Caso o mysqldump esteja em execução , o script pára para não sobrecarregar o servidor.
Caso as tabelas não existam ainda, o script as cria.
O script procura um único banco de cada vez para ser restaurado. Ele faz isso baseado no campo `recorrencia` da tabela `banco`, que pode ser por minuto, hora, dia semana ou mês.
O script monta um arquivo bat, e executa ele em modo background para não travar o PHP e depois atualiza a última execução do backup do banco atual e inclui um log da ação.
