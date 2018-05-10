## Indicadores de TI - Gestão e Descarte de Eventos
Customização do Zabbix para aferição de indicadores de disponibilidade de serviços de TI, com opção de descarte parcial ou total de eventos do cálculo de disponibilidade e geração de relatórios em PDF.

Essa solução foi apresentada por mim na [Zabbix Conference Latin America 2018](http://conference.zabbix.com.br/programacao/).
Se você não assistiu, pode conferir a apresentação em PDF [aqui](.docs/Apresentacao_Zabbix-Conference-LatAm.pdf).

A seguir, procedimentos para instalação e utilização das customizações apresentadas.


### 1) Interface Indicadores de TI

- Localizar o filesystem de instalação do frontend do Zabbix (por exemplo /var/www/zabbix);
- Copiar para esse filesystem os arquivos de dentro da pasta frontend:
  - indicadores_ti.php
  - descarte_parcial.php
  - events.php
- Copiar para dentro do subdiretório /include do frontend do Zabbix o arquivo [funcoes_customizadas](/frontend/include/funcoes_customizadas.php).

#### 1.1) Customização do Menu do Zabbix

Há 3 formas de fazer a customização do menu do Zabbix para incluir o item "Indicadores de TI", que acessará a interface customizada de mesmo nome:

1.1.1) É a maneira mais simples: Se você não possui nenhuma customização de menu no seu Zabbix, basta copiar o arquivo [menu.inc.php](/frontend/include/menu.inc.php) para dentro do subdiretório /include do frontend do Zabbix, substituindo o existente. Mas lembre-se: somente se não tiver nenhuma customização no menu.

Se tiver customizado seu menu, você deverá inserir no arquivo o apontamento para a nova customização. Voce pode fazer isso de duas maneiras:

1.1.2) Executando o patch do menu. Baixe o arquivo [menu.patch](/frontend/include/menu.patch) para o subdiretório /include do 
frontend do Zabbix e depois execute, nesse local, o seguinte comando:

```sh
patch menu.patch
```
1.1.3) Caso essa opção não funcione pra você, deverá inserir manualmente a informação no arquivo de menu.
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

![](./docs/img/IT-Services.png)

