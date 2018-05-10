## Indicadores de TI - Gestão e Descarte de Eventos
Customização do Zabbix para aferição de indicadores de disponibilidade de serviços de TI, com opção de descarte parcial ou total de eventos do cálculo de disponibilidade e geração de relatórios em PDF.

Essa solução foi apresentada por mim na [Zabbix Conference Latin America 2018](http://conference.zabbix.com.br/programacao/).
Se você não assistiu, pode conferir a apresentação em PDF [aqui](.docs/Apresentacao_Zabbix-Conference-LatAm.pdf).

A seguir, procedimentos para instalação e utilização das customizações apresentadas.

### 1) Pré-requisitos

Antes de mais nada, é importante ressaltar que a customização apresentada aqui foi desenvolvida para a versão 3.2.7 do Zabbix e PHP v7.0. Funciona normalmente para outras versões 3.2.x do Zabbix. Porém, se você tem a versão 3.4, talvez tenha que fazer adaptações no código.

Outra questão importante é que as customizações funcionam buscando um padrão definido de estrutura da árvore de IT Services (Serviços de TI)  e nomenclatura de grupos de hosts, e foram desenvolvidas baseadas na estrutura existe no Serpro, onde trabalho.
A estrutura adotada e que vai funcionar em todas as customizações é a seguinte:

#### 1.1 Serviços de TI
No menu configurações, Serviços de TI do Zabbix, você acessa a parte de configuração da árvore de TI Services. A partir da raiz, a árvore deve seguir o seguinte padrão:
São 6 níveis hierárquicos, sendo que a trigger de medição está associada ao último nível, o nome da trigger.

```sh
Serviços 
    <SERVIÇO>
        <CLIENTE>
            <GRUPO-DE-HOSTS>
                <HOST>
                    <TRIGGER>
```

Veja o exemplo abaixo:
![](https://github.com/ramosluciano/Indicadores-de-TI---Descarte-de-Eventos/blob/master/.docs/img/IT-Services2.png)

#### 1.2 Grupos de Hosts

Os grupos de hosts do Zabbix também devem seguir a um padrão:

```sh
UF_SERVIÇO_CLIENTE
Onde:
UF = Unidade federativa de localização do host (Ex. SP, DF, BA, etc);
SERVIÇO = Nome ou sigla do serviço (WAN, LAN, IDC, etc);
CLIENTE = Nome ou sigla do cliente.

Exemplo: SP_WAN_SERPRO
```
Caso você use padrões diferentes desses, você pode alterar as referências nos arquivos PHP do frontend e scripts.



### 2) Interface Indicadores de TI

#### 2.1) Instalação dos arquivos da interface customizada

- Localizar o filesystem de instalação do frontend do Zabbix (por exemplo /var/www/zabbix);
- Copiar para esse filesystem os arquivos de dentro da pasta frontend:
  - indicadores_ti.php
  - descarte_parcial.php
  - events.php
- Copiar para dentro do subdiretório /include do frontend do Zabbix o arquivo [funcoes_customizadas](/frontend/include/funcoes_customizadas.php).

#### 2.2) Customização do Menu do Zabbix

Há 3 formas de fazer a customização do menu do Zabbix para incluir o item "Indicadores de TI", que acessará a interface customizada de mesmo nome:

2.1.1) É a maneira mais simples: Se você não possui nenhuma customização de menu no seu Zabbix, basta copiar o arquivo [menu.inc.php](/frontend/include/menu.inc.php) para dentro do subdiretório /include do frontend do Zabbix, substituindo o existente. Mas lembre-se: somente se não tiver nenhuma customização no menu.

Se tiver customizado seu menu, você deverá inserir no arquivo o apontamento para a nova customização. Voce pode fazer isso de duas maneiras:

2.1.2) Executando o patch do menu. Baixe o arquivo [menu.patch](/frontend/include/menu.patch) para o subdiretório /include do 
frontend do Zabbix e depois execute, nesse local, o seguinte comando:

```sh
patch < menu.patch
```

2.1.3) Caso essa opção não funcione pra você, deverá inserir manualmente a informação no arquivo de menu.
- Edite o arquivo menu.inc.php, que está dentro da pasta /include do seu frontend.
- Localize o índice do array com o arquivo srv_status.php e, ao final dessa cadeia, insira a cadeia abaixo, de modo que fique como na imagem a seguir.

```sh
# MENU CUSTOMIZADO
                                [        
                                        'url' => 'indicadores_ti.php',
                                        'label' => _('Indicadores de TI'),
                                        'sub_pages' => ['events.php', 'descarte_parcial.php']
                                ],
#
```

![](https://github.com/ramosluciano/Indicadores-de-TI---Descarte-de-Eventos/blob/master/.docs/img/IT-Services.png)


### 3 Script de sincronismo de IT Services
(Execute primeiro em laboratório para testar o comportamento)

Copie todo o conteúdo da pasta [scripts](https://github.com/ramosluciano/Indicadores-de-TI---Descarte-de-Eventos/tree/master/scripts) para uma pasta scripts de seu servidor.

Para executar, vá para o diretorio scripts e execute da seguinte forma:

```sh
php sincronizaservico.php URL CLIENTE

Onde:
- URL: Endereço do servidor Zabbix. Pode ser o IP ou a URL mesmo (sem http ou https).
- CLIENTE: Nome ou sigla do cliente, exatamente como está nos IT Services e nos grupos de host
Exemplo: php sincronizaservico.php 10.0.0.1 VIVO

Nota: Na linha 18 do script tem uma chamada API que acessa o Zabbix através dessa URL informada. Observe que no comando a URL está com o protocolo https. Caso seu servidor seja http, altere nessa linha.
Também nesse comando, você deve informar o usuário do Zabbix que tem acesso API aos hosts e a senha de autenticação desse usuário, substituindo nos campos 'USER' e 'PASSWORD'.
```

### 4 Scripts para geração de Relatório de Eventos em PDF

Os arquivos necessários já foram copiados dentro da pasta scripts no Item 3.
O script a ser executado é o relatorio_Zabbix.php. Ele tem chamadas includes para outros arquivos que estarão na mesma pasta.
A sintaxe de execução é a seguinte:

```sh
php relatorio_Zabbix.php CLIENTE DATA-INICIO DATA-FIM SERVIÇO [Descartes]

Onde:

- CLIENTE: Nome ou sigla do cliente, conforme está nos IT Services e Hostgroups;
- DATA-INICIO: Data de inicio do período de aferição, no formato DD/MM/AAAA. Ex: 01/05/2018;
- DATA-FIM: Data de fim do período de aferição, no formato DD/MM/AAAA. Ex: 31/05/2018.
- SERVIÇO: Nome ou sigla do serviço, conforme está nos IT Services e Hostgroups. Ex: LAN, WAN, IDC, etc.;
- [Descartes]: Parâmetro opcional. Se informado, serão listados no relatório os eventos que foram descartados.

Exemplo:
php relatorio_Zabbix.php ITAU 11/04/2018 10/05/2018 WAN

Nota: Por definição, a data de inicio do período de aferição começa À 0h0min e a data de fim do período termina 23h59min.

```

Lembrando mais uma vez que a geração dos dados pode não funcionar perfeitamente no seu ambiente, porque o script foi desenvolvido especificamente para as regras de negócio do Serpro. Talvez tenha que fazer mais alguns ajustes para o seu ambiente.
O script foi desenvolvido em [PHP](php.net) e usando a biblioteca livre FPDF e a API do Zabbix.

No site da [FPDF](http://www.fpdf.org/)  tem um tutorial para ajudar na utilização da biblioteca e scripts de exemplos.
No site do [Zabbix](https://www.zabbix.com) tem uma documentação da [API](https://www.zabbix.com/documentation/3.2/manual/api).
Os arquivos do subdiretório [PhpZabbixApi_Library](https://github.com/ramosluciano/Indicadores-de-TI---Descarte-de-Eventos/tree/master/scripts/PhpZabbixApi_Library) são da biblioteca desenvolvida em PHP para acesso à API do Zabbix por scripts.


## FEEDBACK

Baixou? Usou? Funcionou? Deu pau? Consegui adaptar ao seu ambiente?
Me dê seu feedback!

Muito Obrigado!!!

![](https://github.com/ramosluciano/Indicadores-de-TI---Descarte-de-Eventos/blob/master/.docs/img/emoji.jpeg)

