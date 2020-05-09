<?

class Databaza {
	const PRI_RDBMS = "localhost";  
	const PRI_LOGIN = "root";
	const PRI_HESLO = "";
    const PRI_HLAVNA_DATABAZA = "sulekfhiwzcz1615";
    
    private $db;

	function __construct() {


        $this->VytvorSpojenieNaDatabazu();
        MySQLi_Query($this->db, "set names 'utf8'");
        
    }

    /**
     * Vytvori spojenie na DB
     */
    private function VytvorSpojenieNaDatabazu() {
        
         $this->db = MySQLi_Connect(self::PRI_RDBMS, self::PRI_LOGIN, self::PRI_HESLO, self::PRI_HLAVNA_DATABAZA);
         
		if (mysqli_connect_errno())
		    error_log("[DBE]: Zlyhalo primarne spojenie: ".mysqli_connect_error());
        
    }

   

	function Query($dotaz, $lustraciaTextu=true) {
        // z bezpecnostnych dovodov aby nemohlo byt viac query
        $dotaz = preg_replace('/[;]/', '', $dotaz);


        // odstranime vsetky specialne znaky v query podla charsetu spojenia tak, aby sme z toho urobili platny dotaz
        //$dotaz = mysqli_escape_string ($this->db, $dotaz);
        // $this->Debug("sql query: $dotaz");
        //error_log("[Sql] query: $dotaz");

		$vysledok = MySQLi_Query($this->db, $dotaz);

        // v pripade chyby vracia funkcia MySQLi_Query false
        if (!$vysledok) {
            // ! tento jeden riadkok je tu len kvoli debugu ! - IBA PRE CAS TESTOVANIA
			// $this->Debug("chyba: $dotaz{".mysqli_error($this->db)."}"); // aj tak nefunfuje or mysqli_error($this->db) ;
// 			if ($this->JeVyvojoveProstredie())
// 			    echo ("chyba: $dotaz{".mysqli_error($this->db)."}"); // aj tak nefunfuje or mysqli_error($this->db) ;
			return null;
        }

        // ak nenastala chyba
        if (!mysqli_error($this->db)) {
            // je mozne, ze odpoved je iba true. hocikaje cislo je mozne pretranfromovat vo vyraze ako true.
            // aby sme tomu zamedzili, potrebujeme zistit, ci je vysledok boolean az potom ci je true
            if (is_bool($vysledok)&&($vysledok)) {
                return true;
            // este musime zistit, kolko riadkov je v odpovedi
            // pokial v dotaze bolo insert alebo update ("/(insert)|(update)/i"), tak vratim null a neriesim pocet riadkov,
            // ale ak bolo napr. select tak to riesim...
            } elseif ((!preg_match("/(insert)|(update)/i", $dotaz))&&(mysqli_num_rows($vysledok)>0)) {
                // ak ale existuje aspon 1 riadok (>0), tak vratim vysledok

                // nastavim premenne pre pracu z riadkami
                $this->celkom_riadkov = mysqli_num_rows($vysledok);
                $this->aktualny_riadok = 0;

                return $vysledok;
            } else
                // ak nie je ani jeden riadkok, tak hodim NULL, aby frontend zitil, ze NEEXISTUJE ani jeden zaznam
                //echo "(dotaz: $dotaz)vysledok:"; print_r($vysledok);
                return null;
        }

	}

    // query s jednym riadkom co vraciam ako odpoved
    // --> zatial ale predpokladam happy day vyberu
    // --> do buducnosti tam mozem pridat ze ked to zlyha, niekde to zaloguje alebo vypise... ale to teraz robi metoda Query.. ;-)
	function JednoducheQuery($dotaz) {
        return MySQLi_Fetch_Array($this->Query($dotaz));
    }

    // query s jednou hodnotou co vraciam ako odpoved
    // --> zatial ale predpokladam happy day vyberu
    // --> do buducnosti tam mozem pridat ze ked to zlyha, niekde to zaloguje alebo vypise... ale to teraz robi metoda Query.. ;-)
	function JednohodnotoveQuery($dotaz) {
        $tabulka = $this->Query($dotaz);
        if (!$tabulka)
            return null;
        else
            return MySQLi_Fetch_Array($tabulka)[0];
    }

    


    // obalenie fetcharrayu
    // --> druhy argument $prazdne=null: cim sa ma naplnit prazdne pole
    function StiahniRiadok($vysledok, $prazdne=null) {

        // co ak hodil prazdny $vysledok? tak potom nic...
        if (is_null($vysledok)) return;

        // inkrementujem pocet riadkov v pocitadle riadkov
        $this->aktualny_riadok++;

        if (is_null($prazdne))
            return MySQLi_Fetch_Array($vysledok, MYSQLI_ASSOC);                 // 99% potrebujem iba vytiahnut jeden riadok bez zmeny
        else {
            $riadok = MySQLi_Fetch_Array($vysledok, MYSQLI_ASSOC);
            foreach ($riadok as $stlpec)
                if (is_null($stlpec))
                    $stlpec = $prazdne;
        }

    }

	function __destruct() {
		MySQLi_Close($this->db);
	}


}

?>
