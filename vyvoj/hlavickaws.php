<?php

class SumarneUdaje {
    
	public $eur;
	public $usd;
	public $forex;
	public $krypto;
	public $celkom;
	public $dayMarketValue;
	public $YTDMarketValue;
	
	function __construct() {
		$this->eur = new PolozkaSumaru();
		$this->usd = new PolozkaSumaru();
		$this->forex = new PolozkaSumaru();
		$this->krypto = new PolozkaSumaru();
		$this->celkom = new PolozkaSumaru();
	}
	
}

class PolozkaSumaru {
	
	
	public $market_value = 0.00;
	public $transaction_value = 0.00;
	public $pl = 0.00;
	public $pl_den = 0.00;
	public $pl_ytd = 0.00;
	public $pl_percent = 0.00;
	public $pl_den_percent = 0.00;
	public $pl_ytd_percent = 0.00;

}

class HlavickaWs extends WebovaSluzbaNG {

	private $id_pouzivatela;
	private $id_portfolia;

	const MEDZERA_ZNACIACA_FOREX = " ";
	const MEDZERA_ZNACIACA_KRYPTOMENY = "  ";

	public function __construct() {
		parent::__construct();
	}

	public function Odpoved() {
		if (isset($_GET['id_portfolia']) && isset($_GET['id_pouzivatela'])) {
			$this->id_portfolia = $_GET['id_portfolia'];
			if ($this->id_portfolia==-1) {
				$this->id_portfolia = "";
			} 
			$this->id_pouzivatela = $_GET['id_pouzivatela'];
			// $this->id_typu = MapTypKonstanty::VratIdTypuPodlaMapovehoTypu($_GET["map_typ"]);
			$this->pripojSaNaDatabazu();
			$odpoved = $this->NacitajHlavicku();
			// $odpoved = $this->VratSumarPorfolia();
			return json_encode($odpoved);
		}
	}

	public function getVyberPortfoliaSqlWhere() {
		if (is_numeric($this->id_portfolia)) {
			return " =$this->id_portfolia ";
		} else {
			return " IS NULL ";
		}
	}

	public function NacitajHlavicku() {
		$port = $this->getVyberPortfoliaSqlWhere();
		$sumar = $this->VratSumarPorfolia($port);
		if (isset($_GET["debug"])) {
			print_r($sumar);
		}
		$vrat = $this->upravSumarPortfolia($sumar);
		return $vrat;
	}

	private function upravSumarPortfolia($sumar) {
		// echo "sumar den je ". $sumar->dayMarketValue;
		$udaj = new PolozkaSumaru();
		$udaj->market_value = strval($sumar->celkom->trzna_hodnota);
		$udaj->transaction_value = strval($sumar->celkom->hodnota);
		$udaj->pl = strval($udaj->market_value - $udaj->transaction_value);
		$udaj->pl_percent = strval(( ($udaj->market_value - $udaj->transaction_value) / $udaj->transaction_value ) * 100);
		$udaj->pl_den = strval($udaj->market_value - $sumar->dayMarketValue);
		$udaj->pl_den_percent = strval((($udaj->market_value - $sumar->dayMarketValue) / $sumar->dayMarketValue)*100);
		$udaj->pl_ytd = strval($udaj->market_value - $sumar->YTDMarketValue);
		$udaj->pl_ytd_percent = strval((($udaj->market_value - $sumar->YTDMarketValue) / $sumar->YTDMarketValue)*100);

		if ($sumar->dayMarketValue == 0.0) {
			$udaj->pl_den = "-";
			$udaj->pl_den_percent = "-";
		} 
		if ($sumar->YTDMarketValue == 0.0) {
			$udaj->pl_ytd = "-";
			$udaj->pl_ytd_percent = "-";
		}
		return $udaj;

	}

	private function vratHlavicku($port){
		$rok = date("Y",strtotime("-1 year"));
		$datumYtd = "\"$rok-12-31\"";
		$tabulka_historia_portfolia = "portfolio_historia";
		$podmienka_portfolia = " AND ID_PORTFOLIA = $this->id_portfolia ";
		if ( ($this->id_portfolia== -1) || ($this->id_portfolia == 0) || (!isset($this->id_portfolia)) || ($this->id_portfolia=="") ) {
			$tabulka_historia_portfolia = "portfolio_historia_predvolenych";
			$podmienka_portfolia = "";
		}
			$sql="SELECT SUM(cena_za_akciu*pocet_akcii) AS transaction_value, SUM(pocet_akcii*(
SELECT aktualna_cena
FROM cenny_papier_aktualne_vsetky AS cpa
WHERE cpa.id_cenneho_papiera = pt.id_cenneho_papiera)) AS market_value, SUM(pocet_akcii*(
SELECT aktualna_cena
FROM cenny_papier_aktualne_vsetky AS cpa
WHERE cpa.id_cenneho_papiera = pt.id_cenneho_papiera)) - SUM(cena_za_akciu*pocet_akcii) AS PL,
(SUM(pocet_akcii*(
SELECT aktualna_cena
FROM cenny_papier_aktualne_vsetky AS cpa
WHERE cpa.id_cenneho_papiera = pt.id_cenneho_papiera)) - (
SELECT TRZNA_HODNOTA_USD
FROM $tabulka_historia_portfolia
WHERE id_pouzivatela = $this->id_pouzivatela $podmienka_portfolia AND DATUM = DATE(NOW()- INTERVAL 1 DAY)) ) AS PL_DEN,
(
SELECT TRZNA_HODNOTA_USD
FROM $tabulka_historia_portfolia
WHERE id_pouzivatela = $this->id_pouzivatela $podmienka_portfolia AND  DATUM = DATE($datumYtd)) - SUM(cena_za_akciu*pocet_akcii) AS PL_ROK,
 ((SUM(pocet_akcii*(
SELECT aktualna_cena
FROM cenny_papier_aktualne_vsetky AS cpa
WHERE cpa.id_cenneho_papiera = pt.id_cenneho_papiera)) - SUM(cena_za_akciu*pocet_akcii)) / SUM(cena_za_akciu*pocet_akcii)) * 100 AS PL_PERCENT,
(((((
SUM(pocet_akcii*(
SELECT aktualna_cena
FROM cenny_papier_aktualne_vsetky AS cpa
WHERE cpa.id_cenneho_papiera = pt.id_cenneho_papiera)) - (SELECT TRZNA_HODNOTA_USD
FROM $tabulka_historia_portfolia
WHERE id_pouzivatela = $this->id_pouzivatela $podmienka_portfolia AND DATUM = DATE(NOW()- INTERVAL 1 DAY))))/ (SELECT TRZNA_HODNOTA_USD
FROM $tabulka_historia_portfolia
WHERE id_pouzivatela = $this->id_pouzivatela $podmienka_portfolia AND DATUM = DATE(NOW()- INTERVAL 1 DAY))))*100) AS PL_DEN_PERCENT,
((((
SELECT TRZNA_HODNOTA_USD
FROM $tabulka_historia_portfolia
WHERE id_pouzivatela = $this->id_pouzivatela $podmienka_portfolia AND DATUM = DATE($datumYtd)) - SUM(cena_za_akciu*pocet_akcii))/ SUM(cena_za_akciu*pocet_akcii))*100) AS PL_ROK_PERCENT
FROM portfolio_tranzakcia AS pt
LEFT JOIN cenny_papier cp ON (cp.id_cenneho_papiera = pt.id_cenneho_papiera)
WHERE id_pouzivatela = $this->id_pouzivatela AND ID_portfolia $port";	


		echo $sql;
		$kurzor = $this->data->Query($sql);
		$udaj;
		while($riadok=$this->data->StiahniRiadok($kurzor)) {
			$udaj = $this->NamapujRiadokDbDoJsonFormatu($riadok);
		}
		
		return $udaj;
	}

	private function NamapujRiadokDbDoJsonFormatu($riadok) {
		
		$udaj = new PolozkaSumaru();
		$udaj->market_value = $riadok["market_value"];
		$udaj->transaction_value = $riadok["transaction_value"];
		$udaj->pl = $riadok["PL"];
		$udaj->pl_percent = $riadok["PL_PERCENT"];
		$udaj->pl_den = $riadok["PL_DEN"];
		$udaj->pl_den_percent = $riadok["PL_DEN_PERCENT"];
		$udaj->pl_ytd = $riadok["PL_ROK"];
		$udaj->pl_ytd_percent = $riadok["PL_ROK_PERCENT"];
		
		return $udaj;
	}

	private function Zaokruhli($cislo, $fixneDesatinychMiest=2) {
		if (isset($cislo)) {
			return $this->UpravCisloFixne($cislo,$fixneDesatinychMiest,false);
		} else {
			return null;
		}
	}


	 private function UpravCisloFixne($vstupne_cislo, $miest, $upravitTBMK=true) {
        // if ( ($vstupne_cislo >= 1000) && ($upravitTBMK==true) ) {
            // return self::UpravCisloNaTBMKFormat($vstupne_cislo);     
        // }
   		return number_format(round ($vstupne_cislo, $miest), $miest, ".", " ");
    }

    private function nastavHistorickeCenyPort($vrat) {
    	$denMarket = 0.0;
    	$YTDMarket=0.0;
    	$rok = date("Y",strtotime("-1 year"));
		$datumYtd = "\"$rok-12-31\"";
		$tabulka_historia_portfolia = "portfolio_historia";
		$podmienka_portfolia = " ID_PORTFOLIA = $this->id_portfolia ";
		$podmienka_pouzivatela = "";
		if ( ($this->id_portfolia== -1) || ($this->id_portfolia == 0) || (!isset($this->id_portfolia)) || ($this->id_portfolia=="") ) {
			$tabulka_historia_portfolia = "portfolio_historia_predvolenych";
			$podmienka_portfolia = "";
			$podmienka_pouzivatela = "id_pouzivatela = $this->id_pouzivatela";
		}
    	
    		$sql = " select (
SELECT TRZNA_HODNOTA_USD
FROM $tabulka_historia_portfolia
WHERE $podmienka_pouzivatela $podmienka_portfolia AND DATUM >= DATE(NOW()- INTERVAL 1 month) order by datum desc limit 1 ) as DEN_MARKET ,
(
SELECT TRZNA_HODNOTA_USD
FROM $tabulka_historia_portfolia
WHERE $podmienka_pouzivatela $podmienka_portfolia AND DATUM = DATE($datumYtd)) as YTD_MARKET 
from  $tabulka_historia_portfolia where $podmienka_pouzivatela $podmienka_portfolia"; 
// echo $sql;
	$result=$this->data->Query($sql);
	while ($row = $this->data->StiahniRiadok($result)) {
		// print_r($row);
		$denMarket = $row["DEN_MARKET"];
		$YTDMarket = $row["YTD_MARKET"];
	}
    	$vrat->dayMarketValue = $denMarket;
    	$vrat->YTDMarketValue = $YTDMarket;
    }

    public function VratSumarPorfolia() {
		
		$kurz_eur_usd = $this->data->JednohodnotoveQuery("SELECT AKTUALNA_CENA FROM ".DatabazaKonstanty::TABULKA_AKTUALNYCH_HODNOT." WHERE ID_CENNEHO_PAPIERA=217");

		$port = $this->getVyberPortfoliaSqlWhere();

        $aktualna_cena = "CPA.AKTUALNA_CENA";//$this->VratSpravnyZdrojAktualnejCenyTabuliekMiesanychIdTypov("CPA.AKTUALNA_CENA", "CPA");
		
		$typ_obchodu = "(SELECT OZNACENIE FROM ODPORUCENIE WHERE ID_ODPORUCANIA=MP.TYP_OBCHODU) AS TYP_OBCHODU";
		$sql = "SELECT $typ_obchodu, MP.ID_POLOZKY, CP.ID_CENNEHO_PAPIERA, CP.ID_TYPU, TICKER, NAZOV, 
								ROUND(MP.POCET_AKCII*MP.CENA_ZA_AKCIU, 0) AS HODNOTA,
								ROUND(@aktualna:=$aktualna_cena,4) AS AKTUALNA_CENA,
								ROUND(POCET_AKCII*@aktualna, 0) AS TRZNA_HODNOTA,
                                POCET_AKCII, 
								ROUND(POCET_AKCII * (IF (MP.TYP_OBCHODU=1, (@aktualna-CENA_ZA_AKCIU), (CENA_ZA_AKCIU-@aktualna)  ) )) AS PL,
								ROUND( IF(MP.TYP_OBCHODU=1, ((@aktualna - CENA_ZA_AKCIU) / CENA_ZA_AKCIU ),((CENA_ZA_AKCIU - @aktualna) / CENA_ZA_AKCIU)) * 100, 2) AS PLPERCENT
							FROM REGISTROVANY_POUZIVATEL AS RU 

RIGHT JOIN ".DatabazaKonstanty::TABULKA_MOJE_PORTFOLIO." AS MP ON RU.ID_POUZIVATELA=MP.ID_POUZIVATELA 
LEFT JOIN CENNY_PAPIER AS CP ON CP.ID_CENNEHO_PAPIERA=MP.ID_CENNEHO_PAPIERA 
LEFT JOIN ".DatabazaKonstanty::TABULKA_AKTUALNYCH_HODNOT." AS CPA ON CPA.ID_CENNEHO_PAPIERA=MP.ID_CENNEHO_PAPIERA 

WHERE RU.ID_POUZIVATELA=$this->id_pouzivatela AND MP.ID_PORTFOLIA $port";

		if (isset($_GET["debug"])) {
			echo "SQL: $sql";
		}

		$kurzor = $this->data->Query($sql);
		
		$vrat = new SumarneUdaje();
		$this->nastavHistorickeCenyPort($vrat);
		
		while($polozka=$this->data->StiahniRiadok($kurzor)) {
			
			$mena = $this->ZistiMenu($polozka["ID_TYPU"]);
			switch ($mena) {
				case Slovnik::DOLAR: 
					
					// potrebujem vyratat PL%
					$vrat->usd->hodnota += $polozka["HODNOTA"];
					$vrat->usd->pl += $polozka["PL"];
					
					// pre stlpec Market Value a stlpec Market Value in USD
					$vrat->usd->trzna_hodnota += $polozka["TRZNA_HODNOTA"];
					$vrat->usd->trzna_hodnota_lokalna_mena += $polozka["TRZNA_HODNOTA"];
					
					// pre riadok Total Portfolio Value v $
 					$vrat->celkom->hodnota += $polozka["HODNOTA"];
 					$vrat->celkom->trzna_hodnota += $polozka["TRZNA_HODNOTA"];
						
					break;
				case Slovnik::EUR: 	
					
					// potrebujem vyratat PL%
					$vrat->eur->pl += $polozka["PL"];					
					$vrat->eur->hodnota += $polozka["HODNOTA"];
					
					// pre stlpec Market Value a stlpec Market Value in USD
					$vrat->eur->trzna_hodnota += $polozka["TRZNA_HODNOTA"]*$kurz_eur_usd;
					$vrat->eur->trzna_hodnota_lokalna_mena += $polozka["TRZNA_HODNOTA"];
					
					// pre riadok Total Portfolio Value v $ (kvoli $ prepocet na $)
					$vrat->celkom->hodnota += $polozka["HODNOTA"];
					$vrat->celkom->trzna_hodnota += $polozka["TRZNA_HODNOTA"]*$kurz_eur_usd;

					break;
				case self::MEDZERA_ZNACIACA_FOREX:

					// pripocitame aj pri forexe
					$hodnota_v_usd=0;
					$trzna_hodnota_v_usd=0;
					$ticker=$polozka["TICKER"];
					if ($ticker[4].$ticker[5].$ticker[6]!="USD") {
						// je to menovy par $USD/XXX
						$konverzny_ticker = '$USD'.$ticker[4].$ticker[5].$ticker[6];
						$konverzna_hodnota = $this->VratSQLAktualnejCeny($konverzny_ticker);
					
						$hodnota_v_usd = $polozka["HODNOTA"]/$konverzna_hodnota;
						$trzna_hodnota_v_usd = $polozka["TRZNA_HODNOTA"]/$konverzna_hodnota;
					} else {
						// je to menovy par $XXX/USD
						$hodnota_v_usd = $polozka["HODNOTA"];
						$trzna_hodnota_v_usd = $polozka["TRZNA_HODNOTA"];
					}
					$vrat->forex->hodnota += $hodnota_v_usd;
					$vrat->forex->trzna_hodnota += $trzna_hodnota_v_usd;
					
					// pripocitame aj do celkom
					$vrat->celkom->hodnota += $hodnota_v_usd;
					$vrat->celkom->trzna_hodnota += $trzna_hodnota_v_usd;
					
					//$vrat->eur->pl += $polozka["PL"];
					//$vrat->eur->pl_percent += $polozka["PLPERCENT"];
					//$vrat->celkom->hodnota += $polozka["HODNOTA"]*$kurz_eur_usd;
					//$vrat->celkom->trzna_hodnota += $polozka["TRZNA_HODNOTA"]*$kurz_eur_usd;
					break;
					
				case self::MEDZERA_ZNACIACA_KRYPTOMENY:
					// vychadzam z toho, ze sme tam nechali iba kryptomeny z USD!!!!!!!

					$hodnota_v_usd = 0;
					$trzna_hodnota_v_usd = 0;
					$ticker = $polozka["TICKER"];

					// finta je v tom, ze XYZ/ABC prepocitame z @XYZUSD (vzdy z dolarovej kryptomeny)
					$ticker_usd = '@' . $ticker[1] . $ticker[2] . $ticker[3] . 'USD';
					$hodnota_v_usd = $this->VratSQLAktualnejCeny($ticker_usd);

					$tranzakcna_hodnota_v_usd = $polozka["HODNOTA"]; // $hodnota_v_usd;
					$trzna_hodnota_v_usd =  $polozka["TRZNA_HODNOTA"]; //$hodnota_v_usd;

					// medzivypocet
					$vrat->krypto->hodnota += $tranzakcna_hodnota_v_usd;
					$vrat->krypto->trzna_hodnota += $trzna_hodnota_v_usd;

					// pripocitame aj do celkom
					$vrat->celkom->hodnota += $hodnota_v_usd;
					$vrat->celkom->trzna_hodnota += $trzna_hodnota_v_usd;
				    
				    break;
			}
			
		}
		
		// od 8.2.2017 takto pocitame pl%
		if ($vrat->usd->hodnota!=0)
		    $vrat->usd->pl_percent = ($vrat->usd->pl / $vrat->usd->hodnota)*100.00;
		else
		    $vrat->usd->pl_percent = 0;
		
	    if ($vrat->eur->hodnota!=0)
		   $vrat->eur->pl_percent = ($vrat->eur->pl / $vrat->eur->hodnota)*100.00;
	    else
	        $vrat->eur->pl_percent = 0;
		
		return $vrat;		
	}

	private function ZistiMenu($typ) {
		if ($typ==TypCennehoPapieraKonstanty::FOREX)
			return self::MEDZERA_ZNACIACA_FOREX;
		elseif ($typ==TypCennehoPapieraKonstanty::KRYPTOMENY)
		  return self::MEDZERA_ZNACIACA_KRYPTOMENY;
		elseif (in_array($typ, array(TypCennehoPapieraKonstanty::TELETRADE, TypCennehoPapieraKonstanty::TELETRADE_LP)))
			return Slovnik::EUR;
		else
			return Slovnik::DOLAR;
	}

	private function VratSQLAktualnejCeny($ticker) {
	    return $this->data->JednohodnotoveQuery("SELECT AKTUALNA_CENA FROM ".DatabazaKonstanty::TABULKA_AKTUALNYCH_HODNOT." ".
	                                       "WHERE ID_CENNEHO_PAPIERA=(SELECT ID_CENNEHO_PAPIERA FROM CENNY_PAPIER WHERE TICKER='$ticker')");
	}
	
}
?>
