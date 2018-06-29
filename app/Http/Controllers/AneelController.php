<?php

namespace Crawler\Http\Controllers;


use Carbon\Carbon;
use Crawler\Model\ArangoDb;
use Crawler\Regex\RegexAneel;
use Crawler\StorageDirectory\StorageDirectory;
use Crawler\Util\Util;
use http\Env\Response;
use Illuminate\Http\Request;
use Ixudra\Curl\Facades\Curl;
use ArangoDBClient\ConnectException as ArangoConnectException;
use ArangoDBClient\ClientException as ArangoClientException;
use ArangoDBClient\ServerException as ArangoServerException;
use Goutte\Client;

class AneelController extends Controller
{

    private $regexAneel;
    private $storageDirectory;
    private $arangoDb;

    public function __construct(RegexAneel $regexAneel, StorageDirectory $storageDirectory, ArangoDb $arangoDb)
    {
        $this->regexAneel = $regexAneel;
        $this->storageDirectory = $storageDirectory;
        $this->arangoDb = $arangoDb;
    }

    public function proInfa()
    {


        $response = Curl::to('http://biblioteca.aneel.gov.br/asp/resultadoFrame.asp?modo_busca=legislacao&content=resultado&iBanner=0&iEscondeMenu=0&iSomenteLegislacao=0&iIdioma=0&BuscaSrv=1')
            ->withData(
                array(
                    'leg_campo1' => 'Proinfa' ,
                    'leg_ordenacao' => 'publicacaoDESC',
                    'leg_normas' => '2',
                    'submeteu' => 'legislacao'
                ) )
            ->returnResponseObject()
            ->post();

        $date = Carbon::now();
        $date_format = $date->format('Y-m-d');

        if($response->status == 200) {

            preg_match_all('/td_grid_ficha_background...(.*)/', $this->regexAneel->convert_str($response->content), $matches);


            $material = $this->regexAneel->capturaMaterial($matches[0][0]);
            $norma = $this->regexAneel->capturaNorma($matches[0][0]);
            $data_de_assinatura = $this->regexAneel->capturaDataAssinatura($matches[0][0]);
            $data_de_publicacao = $this->regexAneel->capturaDataPublicacao($matches[0][0]);
            $ementa = $this->regexAneel->capturaEmenta($matches[0][0]);
            $orgao_de_origem = $this->regexAneel->capturaOrgaoDeOriem($matches[0][0]);
            $esfera = $this->regexAneel->capturaEsfera($matches[0][0]);
            $situacao = $this->regexAneel->capturaSituacao($matches[0][0]);
            $texto_integral = $this->regexAneel->capturaTextoIntegral($matches[0][0]); // download
            $voto = $this->regexAneel->capturaVoto($matches[0][0]); //download
            $nota_tecnica = $this->regexAneel->capturaNotaTecnica($matches[0][0]); //download

            $download_texto_integral = [];
            $download_voto = [];
            $download_nota_tecnica = [];

//dd($texto_integral);


            $filtra = function ($n) {

                $stripos = function ($item) {
                    $ement = stripos($item, 'taquari');
                    if ($ement) {
                        return $item;
                    }
                };

                $check = array_map($stripos, $n);

                return $check;
            };

            $ementaFilt= array_map($filtra, $ementa);

            foreach ($ementaFilt as $key => $item) {
//dd($item);
//                            if (isset ($texto_integral[$key]['texto_integral']))}
//dd($texto_integral[$key]['texto_integral']);

                if ($ementaFilt[$key]['Ementa']) {
                    $results_download_integral = Curl::to($texto_integral[$key]['texto_integral'])
                        ->withContentType('application/pdf')
                        ->download('');
//dd($results_download_integral);
                    $download_texto_integral = $this->storageDirectory->saveDirectory('aneel/texto_integral/' . $date_format . '/', $texto_integral[$key]['name_arquivo'], $results_download_integral);
                } else {
                    $download_texto_integral = array_merge($download_texto_integral, ['vazio']);
                }
//                        }
            }

            dd($download_texto_integral);
//                    if (isset($voto[$key]['voto'])) {
//                        $results_download_voto = Curl::to($voto[$key]['voto'])
//                            ->withContentType('application/pdf')
//                            ->download('');
//                        $download_voto = $this->storageDirectory->saveDirectory('aneel/voto/' . $date_format . '/', $voto[$key]['name_arquivo'], $results_download_voto);
//                    } else {
//                        $download_voto = array_merge($download_voto, ['vazio']);
//                    }
//                    if (isset($nota_tecnica[$key]['nota_tecnica'])) {
//                        $results_download_nota_tecnica = Curl::to($nota_tecnica[$key]['nota_tecnica'])
//                            ->withContentType('application/pdf')
//                            ->download('');
//                        $download_nota_tecnica = $this->storageDirectory->saveDirectory('aneel/nota_tecnica/' . $date_format . '/', $nota_tecnica[$key]['name_arquivo'], $results_download_nota_tecnica);
//
//                    } else {
//                        $download_nota_tecnica = array_merge($download_nota_tecnica, ['vazio']);
//                    }


//                foreach ($material as $key => $item) {
//                    $results[$key] = [
//                        'url_arquivo_nota_tecnica' => $download_nota_tecnica[$key],
//                        'url_arquivo_voto' => $download_voto[$key],
//                        'url_arquivo_texto_integral' => $download_texto_integral[$key]
//                    ];
//                    $resultados[] = array_merge($material[$key], $norma[$key], $data_de_assinatura[$key], $data_de_publicacao[$key], $ementa[$key], $orgao_de_origem[$key], $esfera[$key], $situacao[$key], $results[$key]);
//                }
//                // ------------------------------------------------------------------------Crud--------------------------------------------------------------------------------------------------
//
//                try {
//                    if ($this->arangoDb->collectionHandler()->has('aneel')) {
//
//                        $this->arangoDb->documment()->set('proInfa', [$date_format => $resultados]);
//                        $this->arangoDb->documentHandler()->save('aneel', $this->arangoDb->documment());
//
//                    } else {
//
//                        // create a new collection
//                        $this->arangoDb->collection()->setName('aneel');
//                        $this->arangoDb->collectionHandler()->create($this->arangoDb->collection());
//
//                        // create a new documment
//                        $this->arangoDb->documment()->set('proInfa', [$date_format => $resultados]);
//                        $this->arangoDb->documentHandler()->save('aneel', $this->arangoDb->documment());
//                    }
//                } catch (ArangoConnectException $e) {
//                    print 'Connection error: ' . $e->getMessage() . PHP_EOL;
//                } catch (ArangoClientException $e) {
//                    print 'Client error: ' . $e->getMessage() . PHP_EOL;
//                } catch (ArangoServerException $e) {
//                    print 'Server error: ' . $e->getServerCode() . ':' . $e->getServerMessage() . ' ' . $e->getMessage() . PHP_EOL;
//                }
//
//                return response()->json(
//                    [
//                        'site' => 'www.biblioteca.aneel.gov.br',
//                        'palavra_chave' => 'proinfa',
//                        'responsabilidade' => 'O crawler pega todos os registros do site e realiza os downloads dos mesmos!',
//                        'status' => 'Crawler Aneel realizado com sucesso!'
//                    ]
//                );
//            }else {
//                return response()->json([
//                    'site' => 'www.biblioteca.aneel.gov.br',
//                    'responsabilidade' => 'O crawler pega todos os registros do site e realiza os downloads dos mesmos!',
//                    'status' => 'O crawler não encontrou o arquivo especificado!'
//                ]);
        }

    }
    public function contaDesenvEnerg()
    {


        $response = Curl::to('http://biblioteca.aneel.gov.br/asp/resultadoFrame.asp?modo_busca=legislacao&content=resultado&iBanner=0&iEscondeMenu=0&iSomenteLegislacao=0&iIdioma=0&BuscaSrv=1')
            ->withData(
                array(
                    'leg_campo1' => 'conta desenvolvimento energetico' ,
                    'leg_ordenacao' => 'publicacaoDESC',
                    'leg_normas' => '2',
                    'submeteu' => 'legislacao'
                ) )
            ->returnResponseObject()
            ->post();

        $date = Carbon::now();
        $date_format = $date->format('Y-m-d');

        if($response->status == 200) {

            preg_match_all('/td_grid_ficha_background...(.*)/', $this->regexAneel->convert_str($response->content), $matches);

            $material = $this->regexAneel->capturaMaterial($matches[0][0]);
            $norma = $this->regexAneel->capturaNorma($matches[0][0]);
            $data_de_assinatura = $this->regexAneel->capturaDataAssinatura($matches[0][0]);
            $data_de_publicacao = $this->regexAneel->capturaDataPublicacao($matches[0][0]);
            $ementa = $this->regexAneel->capturaEmenta($matches[0][0]);
            $orgao_de_origem = $this->regexAneel->capturaOrgaoDeOriem($matches[0][0]);
            $esfera = $this->regexAneel->capturaEsfera($matches[0][0]);
            $situacao = $this->regexAneel->capturaSituacao($matches[0][0]);
            $voto = $this->regexAneel->capturaVoto($matches[0][0]);
            $texto_integral = $this->regexAneel->capturaTextoIntegral($matches[0][0]); // download
            $nota_tecnica = $this->regexAneel->capturaNotaTecnica($matches[0][0]);

            $download_texto_integral = [];
            $download_voto = [];
            $download_nota_tecnica = [];

            foreach ($norma as $key => $item) {

                if (isset($texto_integral[$key]['texto_integral'])) {
                    $results_download_integral = Curl::to($texto_integral[$key]['texto_integral'])
                        ->withContentType('application/pdf')
                        ->download('');
                    $download_texto_integral = $this->storageDirectory->saveDirectory('aneel/texto_integral/'.$date_format.'/', $texto_integral[$key]['name_arquivo'], $results_download_integral);
                } else {
                    $download_texto_integral = array_merge($download_texto_integral, ['vazio']);
                }
                if (isset($voto[$key]['voto'])) {
                    $results_download_voto = Curl::to($voto[$key]['voto'])
                        ->withContentType('application/pdf')
                        ->download('');
                    $download_voto = $this->storageDirectory->saveDirectory('aneel/voto/'.$date_format.'/', $voto[$key]['name_arquivo'], $results_download_voto);
                } else {
                    $download_voto = array_merge($download_voto, ['vazio']);
                }
                if (isset($nota_tecnica[$key]['nota_tecnica'])) {
                    $results_download_nota_tecnica = Curl::to($nota_tecnica[$key]['nota_tecnica'])
                        ->withContentType('application/pdf')
                        ->download('');
                    $download_nota_tecnica = $this->storageDirectory->saveDirectory('aneel/nota_tecnica/'.$date_format.'/', $nota_tecnica[$key]['name_arquivo'], $results_download_nota_tecnica);
                } else {
                    $download_nota_tecnica = array_merge($download_nota_tecnica, ['vazio']);
                }
            }
            foreach ($material as $key => $item) {
                $results[$key] = [
                    'url_arquivo_nota_tecnica' => $download_nota_tecnica[$key],
                    'url_arquivo_voto' => $download_voto[$key],
                    'url_arquivo_texto_integral' => $download_texto_integral[$key]
                ];
                $resultados[]['file'] = array_merge($material[$key], $norma[$key], $data_de_assinatura[$key], $data_de_publicacao[$key], $ementa[$key], $orgao_de_origem[$key], $esfera[$key], $situacao[$key], $results[$key]);
            }
            // ------------------------------------------------------------------------Crud--------------------------------------------------------------------------------------------------

            try {
                if ($this->arangoDb->collectionHandler()->has('aneel')) {

                    $this->arangoDb->documment()->set('conta_desenvolvimento_energetico', [$date_format => $resultados]);
                    $this->arangoDb->documentHandler()->save('aneel', $this->arangoDb->documment());

                } else {
                    // create a new collection
                    $this->arangoDb->collection()->setName('aneel');
                    $this->arangoDb->collectionHandler()->create($this->arangoDb->collection());
                    // create a new documment
                    $this->arangoDb->documment()->set('conta_desenvolvimento_energetico', [$date_format => $resultados]);
                    $this->arangoDb->documentHandler()->save('aneel', $this->arangoDb->documment());
                }
            } catch (ArangoConnectException $e) {
                print 'Connection error: ' . $e->getMessage() . PHP_EOL;
            } catch (ArangoClientException $e) {
                print 'Client error: ' . $e->getMessage() . PHP_EOL;
            } catch (ArangoServerException $e) {
                print 'Server error: ' . $e->getServerCode() . ':' . $e->getServerMessage() . ' ' . $e->getMessage() . PHP_EOL;
            }

            return response()->json(
                [
                    'site' => 'www.biblioteca.aneel.gov.br',
                    'palavra_chave' => 'conta desenvolvimento energetico',
                    'responsabilidade' => 'O crawler pega todos os registros do site e realiza os downloads dos mesmos!',
                    'status' => 'Crawler Aneel realizado com sucesso!'
                ]
            );

        }else{
            return response()->json([
                'site' => 'www.biblioteca.aneel.gov.br',
                'responsabilidade' => 'O crawler pega todos os registros do site e realiza os downloads dos mesmos!',
                'status' => 'O crawler não encontrou o arquivo especificado!'
            ]);
        }

    }


    public function cdeAudiencia( Client $client)
    {
        $date_format = Util::getDateIso();

        $url_base_1 = "http://www.aneel.gov.br/audiencias-publicas?p_p_id=audienciaspublicasvisualizacao_WAR_AudienciasConsultasPortletportlet&p_p_lifecycle=0&p_p_state=normal&p_p_mode=view&p_p_col_id=column-2&p_p_col_count=1&_audienciaspublicasvisualizacao_WAR_AudienciasConsultasPortletportlet_situacao=1&_audienciaspublicasvisualizacao_WAR_AudienciasConsultasPortletportlet_objetivo=conta de desenvolvimento energetico";
        $url_base_2 ="http://www.aneel.gov.br/audiencias-publicas?p_p_id=audienciaspublicasvisualizacao_WAR_AudienciasConsultasPortletportlet&p_p_lifecycle=0&p_p_state=normal&p_p_mode=view&p_p_col_id=column-2&p_p_col_count=1";
        $client->request('GET', $url_base_1, array('allow_redirects' => true));
        $get_response_site = $client->getResponse();
        $client->getCookieJar();

        if($get_response_site->getStatus() == 200)
        {

            $results = explode('<td class="table-cell only">', $get_response_site);
            $div_array = array_slice($results, 1);


            $result_url = $this->regexAneel->capturaAudiencia($div_array[0]);
            $url_redirect = $client->request('GET', $result_url, array('allow_redirects' => true));
            $nota_tecnica_link_download = $this->regexAneel->capturaResultados($url_redirect->html());
            $get_data_de_contribuicao = $this->regexAneel->capturaDataDeContribuicao($url_redirect->html());

            $client->request('GET',$nota_tecnica_link_download);

            $download_nota_tecnica = $this->storageDirectory->saveDirectory('aneel/audiencia_publicas/nota_tecnica/'.$date_format.'/', 'audiencias-publicas_'.$get_data_de_contribuicao.'.pdf', $client->getResponse());

            // ------------------------------------------------------------------------Crud--------------------------------------------------------------------------------------------------

            try {
                if ($this->arangoDb->collectionHandler()->has('aneel')) {

                    $this->arangoDb->documment()->set('audiencia_publicas', [$date_format => $download_nota_tecnica]);
                    $this->arangoDb->documentHandler()->save('aneel', $this->arangoDb->documment());

                } else {
                    // create a new collection
                    $this->arangoDb->collection()->setName('aneel');
                    $this->arangoDb->collectionHandler()->create($this->arangoDb->collection());
                    // create a new documment
                    $this->arangoDb->documment()->set('audiencia_publicas', [$date_format => $download_nota_tecnica]);
                    $this->arangoDb->documentHandler()->save('aneel', $this->arangoDb->documment());
                }
            } catch (ArangoConnectException $e) {
                print 'Connection error: ' . $e->getMessage() . PHP_EOL;
            } catch (ArangoClientException $e) {
                print 'Client error: ' . $e->getMessage() . PHP_EOL;
            } catch (ArangoServerException $e) {
                print 'Server error: ' . $e->getServerCode() . ':' . $e->getServerMessage() . ' ' . $e->getMessage() . PHP_EOL;
            }

            return response()->json(
                [
                    'site' => 'http://www.aneel.gov.br/audiencias-publicas',
                    'palavra_chave' => 'conta desenvolvimento energetico aba encerrados',
                    'responsabilidade' => 'O crawler realiza o download do arquivo nota tecnica!',
                    'status' => 'Crawler Aneel realizado com sucesso!'
                ]
            );

        }else{
            return response()->json([
                'site' => 'http://www.aneel.gov.br/audiencias-publicas',
                'responsabilidade' => 'O crawler realiza o download do arquivo nota tecnica!',
                'status' => 'O crawler não encontrou o arquivo especificado!'
            ]);
        }
    }

    public function expansaoGeracao( Client $client)
    {

        $url_base = "http://www.aneel.gov.br";
        $url = "http://www.aneel.gov.br/acompanhamento-da-expansao-da-oferta-de-geracao-de-energia-eletrica";

        $client->request('GET', $url, array('allow_redirects' => true));
        $response = $client->getResponse();
        $client->getCookieJar();

        $date = $this->regexAneel->getDataExpansao($response);

//        if($response->status == 200){

        $peqCentrais = $url_base.$this->regexAneel->getPequenasCentrais($response);
        $eolicas = $url_base.$this->regexAneel->getEolicas($response);
        $hidreletricas = $url_base.$this->regexAneel->getHidreletricas($response);
        $termeletricas = $url_base.$this->regexAneel->getTermeletricas($response);
        $biomassa = $url_base.$this->regexAneel->getBiomassa($response);
        $fotovoltaicas = $url_base.$this->regexAneel->getFotovoltaicas($response);
        $resumo = $url_base.$this->regexAneel->getResumo($response);


        if (isset ($peqCentrais)){
            $download_peqCentrais = Curl::to($peqCentrais)
                ->withContentType('application/pdf')
                ->download('');

            $result['file'] = $this->storageDirectory->saveDirectory('aneel/mensal/'.$date,'pequenas_centrais_hidreletricas.pdf', $download_peqCentrais);
        }

        if (isset ($eolicas)){
            $download_eolicas = Curl::to($hidreletricas)
                ->withContentType('application/pdf')
                ->download('');

            $result['file'] = $this->storageDirectory->saveDirectory('aneel/mensal/'.$date,'usinas_eolicas.pdf', $download_eolicas);
        }

        if (isset ($hidreletricas)){
            $download_hidreletricas = Curl::to($hidreletricas)
                ->withContentType('application/pdf')
                ->download('');

            $result['file'] = $this->storageDirectory->saveDirectory('aneel/mensal/'.$date,'usinas_hidreletricas.pdf', $download_hidreletricas);
        }

        if (isset ($termeletricas)){
            $download_termeletricas = Curl::to($termeletricas)
                ->withContentType('application/pdf')
                ->download('');

            $result['file'] = $this->storageDirectory->saveDirectory('aneel/mensal/'.$date,'usinas_termeletricas.pdf', $download_termeletricas);
        }

        if (isset ($biomassa)){
            $download_biomassa = Curl::to($biomassa)
                ->withContentType('application/pdf')
                ->download('');

            $result['file'] = $this->storageDirectory->saveDirectory('aneel/mensal/'.$date,'usinas_temeletricas_a_biomassa.pdf', $download_biomassa);
        }

        if (isset ($fotovoltaicas)){
            $download_fotovoltaicas= Curl::to($fotovoltaicas)
                ->withContentType('application/pdf')
                ->download('');

            $result['file'] = $this->storageDirectory->saveDirectory('aneel/mensal/'.$date,'usinas_fotovoltaicas.pdf', $download_fotovoltaicas);
        }

        if (isset ($resumo)){
            $download_resumo = Curl::to($resumo)
                ->withContentType('application/xlsx')
                ->download('');

            $result['file'] = $this->storageDirectory->saveDirectory('aneel/mensal/'.$date,'resumo_geral.xlsx', $download_resumo);
        }


        try {
            if ($this->arangoDb->collectionHandler()->has('aneel')) {

                $this->arangoDb->documment()->set('expansao_da_oferta_de_geracao_de_energia_eletrica', [$date => $result]);
                $this->arangoDb->documentHandler()->save('aneel', $this->arangoDb->documment());
            } else {
                $this->arangoDb->collection()->setName('aneel');
                $this->arangoDb->collectionHandler()->create($this->arangoDb->collection());

                $this->arangoDb->documment()->set('expansao_da_oferta_de_geracao_de_energia_eletrica', [$date => $result]);
                $this->arangoDb->documentHandler()->save('aneel', $this->arangoDb->documment());
            }



        }  catch
        (ArangoConnectException $e) {
            print 'Connection error: ' . $e->getMessage() . PHP_EOL;
        } catch (ArangoClientException $e) {
            print 'Client error: ' . $e->getMessage() . PHP_EOL;
        } catch (ArangoServerException $e) {
            print 'Server error: ' . $e->getServerCode() . ':' . $e->getServerMessage() . ' ' . $e->getMessage() . PHP_EOL;
        }

        return response()->json(
            [
                'site' => 'http://www.aneel.gov.br/audiencias-publicas',
                'palavra_chave' => 'conta desenvolvimento energetico aba encerrados',
                'responsabilidade' => 'O crawler realiza o download do arquivo nota tecnica!',
                'status' => 'Crawler Aneel realizado com sucesso!'
            ]);
//        }         else{
//                    return response()->json([
//                        'site' => 'http://www.aneel.gov.br/audiencias-publicas',
//                        'responsabilidade' => 'O crawler realiza o download do arquivo nota tecnica!',
//                        'status' => 'O crawler não encontrou o arquivo especificado!'
//                    ]);
//                }




    }

  public function cegGeracao (Client $client)
    {
        $date = Carbon::now()->format('Y-m-d');

        $url_base = "http://www2.aneel.gov.br/scg/consulta_empreendimento.asp?acao=BUSCAR&pagina=&IdTipoGeracao=&IdFaseUsina=&CodCIE=&NomeEmpreendimento=";

        $response_base  = Curl::to($url_base )
            ->allowRedirect(true)
            ->withTimeout(1000)
            ->get();

        $total = $this->regexAneel->getTotal($response_base );

        $ultima = ceil($total);

       for($pagina = 1; $pagina <= $ultima; $pagina++)
        {
            $url = 'http://www2.aneel.gov.br/scg/consulta_empreendimento.asp?acao=BUSCAR&pagina=' . $pagina . '&IdTipoGeracao=&IdFaseUsina=&CodCIE=&NomeEmpreendimento=';

            $response = Curl::to($url)
                ->allowRedirect(true)
                ->withTimeout(1000)
                ->get();

            $ceg = $this->regexAneel->getCeg($response);
            $tipo = $this->regexAneel->getTipo($response);
            $empreendimento = $this->regexAneel->getEmpreendimento($response);
            $potencia = $this->regexAneel->getPotencia($response);
            $garantia = $this->regexAneel->getGarantia($response);
            $fonte = $this->regexAneel->getFonte($response);
            $rio = $this->regexAneel->getRio($response);
            $data = $this->regexAneel->getData($response);
            $situacao = $this->regexAneel->getSituacao($response);
            $proprietario = $this->regexAneel->getProprietario($response);
            $municipio = $this->regexAneel->getMunicipio($response);

                $dados = [];
                foreach ($ceg as $key => $item)
                {
                    $dados[$key] = array_merge
                    (
                        $ceg[$key],
                        $tipo[$key],
                        $empreendimento[$key],
                        $potencia[$key],
                        $garantia[$key],
                        $fonte[$key],
                        $rio[$key],
                        $data[$key],
                        $situacao[$key],
                        $proprietario[$key],
                        $municipio[$key]
                    );
                }
        }
dd($dados);
//echo '<pre>', var_dump($dados);
//die;

//      try {
//          if ($this->arangoDb->collectionHandler()->has('aneel')) {
//
//              $this->arangoDb->documment()->set('usinas', [$date => $dados]);
//              $this->arangoDb->documentHandler()->save('aneel', $this->arangoDb->documment());
//          } else {
//              $this->arangoDb->collection()->setName('aneel');
//              $this->arangoDb->collectionHandler()->create($this->arangoDb->collection());
//
//              $this->arangoDb->documment()->set('usinas', [$date => $dados]);
//              $this->arangoDb->documentHandler()->save('aneel', $this->arangoDb->documment());
//          }
//
//
//      }  catch
//      (ArangoConnectException $e) {
//          print 'Connection error: ' . $e->getMessage() . PHP_EOL;
//      } catch (ArangoClientException $e) {
//          print 'Client error: ' . $e->getMessage() . PHP_EOL;
//      } catch (ArangoServerException $e) {
//          print 'Server error: ' . $e->getServerCode() . ':' . $e->getServerMessage() . ' ' . $e->getMessage() . PHP_EOL;
//      }
//
//      return response()->json(
//          [
//              'site' => 'http://www.aneel.gov.br/audiencias-publicas',
//              'palavra_chave' => 'conta desenvolvimento energetico aba encerrados',
//              'responsabilidade' => 'O crawler realiza o download do arquivo nota tecnica!',
//              'status' => 'Crawler Aneel realizado com sucesso!'
//          ]);

    }

}
