# refreshResource - 人工資源補充プラグイン for pmmp4
生活サーバー向け、ブロックをタップする・もしくはコマンドで人工資源を補充するプラグインです。

## 特徴

- 直観的でわかりやすく操作できます
  - 設定はすべてコマンド上で行えます 
- あらかじめ設定したブロック(看板)を2回タップすると人工資源を補充できます
- OPはコマンドで人工資源を補充することができます
- 補充エリア内にほかのブロックがある・補充エリア内に人がいる・資源が50ブロック以上掘られていない(資源の体積が50ブロック以下の時は2/3以上掘られていない)場合は補充されません

## コマンド

|       コマンド       | 概要                                                                        | デフォルト権限 |
|:----------------:|:--------------------------------------------------------------------------|:--------|
| /ref <ID/reload> | /ref <ID> で人工資源を補充します<br>/ref reload で設定ファイルを再読み込みします<br>/ref でIDの一覧を見れます | OPのみ    |
|     /addref      | 人工資源の設定を追加します                                                             | OPのみ    |
|   /delref <ID>   | 人工資源の設定を削除します                                                             | OPのみ    |

## 使用方法

refreshResource.phar もしくは refreshResourceフォルダ をpluginsフォルダの中に入れてください。refreshResourceフォルダで使用する場合は、.pharとしてアーカイブ化されていないファイルを読み込むために[DevTools](https://poggit.pmmp.io/p/DevTools)が必要です。
