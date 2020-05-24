<?php
require_once("databaza.php");

class tableRow {
	public $sumy=array();

	function __construct($pocetStlpcov) {
		for ($i=0; $i < $pocetStlpcov; $i++) {
			array_push($this->sumy, 0);
		}
	}

}

class urovenCasu {

	function __construct($group, $concat) {
		$this->group=$group;
		$this->concat=$concat;
	}
	public $group;
	public $concat;
}

class Vypis {
	private $db;

	private $stlpce=array();

	private $min =-1;
	private $max=0.0;
	private $pocet = 0;
	private $dokopy = 0.0;
	private $priemer = 0.0;
	private $casovaUroven = "mesiac/rok";
	private $nazovCasu = "";
	private $regiony = array();


	function __construct() {
		$this->db = new Databaza();
		$this->vypisStyly();
	}

	public function zobrazSumar() {
		$NazovRegionov = $this->vratNazovRegionov($_GET["uroven_regionu"]);
		$nazovCasu = $this->vratNazovCasu($_GET["uroven_casu"]);
		$rok = "za rok ".$_GET["rok"];
		if ($_GET["uroven_casu"]=="rok"){
			$rok = "";
		}

		$this->vypisSumarPrijmu("Kontingenca tabulka prijmov rozdelenych podla $nazovCasu a $NazovRegionov $rok");
		echo "";
	}

	private function vratNazovRegionov($slovo) {
		switch ($slovo) {
			case 'stat':
				return "statov";
				break;
			case 'mesto':
				return "miest";
				break;
			case 'region':
				return "regionov";
				break;
			default:
				return "";
				break;
		}
	}

	private function vratNazovCasu($uroven){
		switch ($uroven) {
			case 'mesiac':
				return "mesiacov";
				break;

			case 'rok':
				return "rokov";
				break;

			case 'den':
				return "dni";
				break;

			case 'tyzden':
				return "tyzdnov";
				break;


			default:
				return "";
				break;
		}
	}

	private function vratNazovTypu($uroven) {
	    switch ($uroven) {
	        case 'skupina' :
	            return "skupin";
	        case 'typ' :
	            return "typov";
	        case 'produkt';
	           return "produktov";
	        default :
	            return "";
	            break;
	    }
	}

	private function vypisSumarPrijmu($nadpis="") {
		echo "<h3>$nadpis</h3>";
		$riadky = $this->vratUdajePreSumar();
		$this->ZobrazJadroTabulky($riadky);
		$this->KoniecTabulky();
		$this->nastavStredneHodnoty(null,null,null);
	}

	private function ZobrazJadroTabulky($riadky) {
		foreach ($riadky as $key => $value) {
			$row=array();
			array_push($row, $key);

			foreach ($value->sumy as $index=> $stlpec) {
				$this->nastavStredneHodnoty($key, $this->stlpce[$index], $stlpec);
				array_push($row, $stlpec);
			}
			$this->RiadokTabulky($row,$key);
		}
	}

	private function vratUdajePreSumar() {
		$rok = $_GET["rok"];
		$uroven_casu = $this->nastavUrovenCasu($_GET["uroven_casu"]);
		$urovenRegionu = $_GET["uroven_regionu"];
		$podmienkaCasu= " YEAR(DATUMCAS) = $rok ";
		if ($_GET["uroven_casu"]=="rok"){
			$podmienkaCasu="1";
		}


		$sql = "select p.ID_POLOZKY, pp.$urovenRegionu, CONCAT($uroven_casu->concat ) as mesiac_prijmu, SUM(SUMA) as CENA from prijem  as p
		left join polozka_prijmu as pp on (pp.ID_POLOZKY = p.ID_POLOZKY)
		 where $podmienkaCasu
		GROUP BY $uroven_casu->group, pp.$urovenRegionu ";
		// echo $sql;

		$result = $this->db->Query($sql);
		$this->vratPolozkyPodlaRegionov();
		$riadky=array();
		while ($riadok = $this->db->StiahniRiadok($result)) {
			$riadky=$this->pridajHodnotuDoRiadku($riadok,$riadky);
		}
		return $riadky;
	}

	private function nastavUrovenCasu($uroven){
		switch ($uroven) {
			case 'mesiac':
				$this->casovaUroven="mesiac/rok";
				$this->nazovCasu="mesiacov";

				return new urovenCasu("MONTH(DATUMCAS)","MONTH(DATUMCAS) ,'/',YEAR(DATUMCAS)");

				break;

			case 'rok':
				$this->casovaUroven="rok";
				$this->nazovCasu="rokov";
				return new urovenCasu("YEAR(DATUMCAS)","YEAR(DATUMCAS)");
				break;

			case 'den':
				$this->casovaUroven="den/mesiac/rok";
				$this->nazovCasu="dni";
				return new urovenCasu("YEAR(DATUMCAS), MONTH(DATUMCAS), DAY(DATUMCAS)","DAY(DATUMCAS),'/',MONTH(DATUMCAS) ,'/',YEAR(DATUMCAS)");
				break;

			case 'tyzden':
				$this->nazovCasu="tyzdnov";
				$this->casovaUroven="tyzden/rok";
				return new urovenCasu("WEEK(DATUMCAS)","WEEK(DATUMCAS) ,'/',YEAR(DATUMCAS)");
				break;


			default:
				return "";
				break;
		}
	}



	private function pridajHodnotuDoRiadku($riadok, $riadky) {
		$urovenRegionu = $_GET["uroven_regionu"];
		$indexStlpca = array_search($riadok[$urovenRegionu], $this->stlpce);

		if (array_key_exists($riadok["mesiac_prijmu"], $riadky)){
				$riadky[$riadok["mesiac_prijmu"]]->sumy[$indexStlpca-1] += $riadok["CENA"];

		} else {
			$row = new tableRow(count($this->stlpce) -1);
			$row->sumy[$indexStlpca-1] = $riadok["CENA"];
			$riadky[$riadok["mesiac_prijmu"]] = $row;

		}
		return $riadky;
	}

	private function nastavStredneHodnoty($datum=null, $region=null, $cena=null) {
		if (!isset($cena)) {
			$this->vypocitajPriemer();
			$this->vypisStredneHodnoty();
			return null;
		}
		if ($this->min==-1) {
			$this->min = $cena;
		}
		if ($this->min>= $cena) {
			$this->min= $cena;
		}
		if ($this->max <= $cena) {
			$this->max = $cena;
		}
		$this->pocet++;
		$this->dokopy += $cena;
	}

	private function vypocitajPriemer() {
		$this->priemer = $this->dokopy / $this->pocet;

	}
	private function vypisStredneHodnoty() {
		echo "<h4>";
		// echo "Celkovy pocet poloziek prijmu: $this->pocet <br>";
		$priemer = round($this->priemer,2);
		echo "Celkova suma poloziek prijmu: $this->dokopy <br>";
		echo "Minimum poloziek prijmu: $this->min <br>";
		echo "Maximum poloziek prijmu: $this->max <br>";
		echo "Priemer poloziek prijmu: $priemer <br>";
		echo "<br> (Pre zobrazenie kolacikoveho grafu kliknite na lubovolny datum v tabulke)<br>";
		echo "</h4>";
	}



	private function vratPolozkyPodlaRegionov() {
		$sql = "select * from polozka_prijmu;";
		$urovenRegionu = strtoupper($_GET["uroven_regionu"]);
		$result = $this->db->Query($sql);
		$regiony=array($this->casovaUroven);

		while ($riadok = $this->db->StiahniRiadok($result)) {
    		if(!in_array($riadok[$urovenRegionu], $regiony)) {
    			array_push($regiony, $riadok[$urovenRegionu]);
    		}

		}
		$this->stlpce= $regiony;
		// print_r($this->stlpce);
		$this->HlavickaTabulky($regiony);

	}


	private function HlavickaTabulky ($hlavicka=array()) {
		echo "<table id='input' class='tabulka'><thead><tr>";
		foreach ($hlavicka as $stlpec) {
		echo "<th class='region'>$stlpec</th>";
		}
		echo "</tr></thead><tbody>";
	}

	private function RiadokTabulky ($riadok, $key){
	 	$kolkyStlpec = 0;
		echo "<tr id='$key'>";
		foreach ($riadok as $prvok) {
			$identifikatorStlpca=$this->stlpce[$kolkyStlpec];
			if ($prvok == $key) {
				echo "<td id='datum_$key' onclick='zobrazPieChart(\"$key\")'>$key</td>";
			} else {
				echo "<td class='$identifikatorStlpca'>$prvok</td>";
			}
			$kolkyStlpec++;
		}
		echo "</tr>";
	}

	private function KoniecTabulky() {
		echo "</tbody></table>";
	}

	private function vypisStyly() {
		echo '<style>
		.tabulka {
  font-family: "Trebuchet MS", Arial, Helvetica, sans-serif;
  border-collapse: collapse;
  width: 100%;
}

.tabulka td, .tabulka th {
  border: 1px solid #ddd;
  padding: 8px;
}

.tabulka tr:nth-child(even){background-color: #f2f2f2;}

.tabulka tr:hover {background-color: #ddd;}

.tabulka th {
  padding-top: 12px;
  padding-bottom: 12px;
  text-align: left;
  background-color: #4CAF50;
  color: white;
}</style>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/d3/3.5.5/d3.min.js"></script>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui-touch-punch/0.2.3/jquery.ui.touch-punch.min.js"></script>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/4.1.2/papaparse.min.js"></script>
        <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/c3/0.4.10/c3.min.css">
		<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/d3/3.5.5/d3.min.js"></script>
		<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/c3/0.4.10/c3.min.js"></script>
        <script src="https://cdn.plot.ly/plotly-basic-latest.min.js"></script>
        <script type="text/javascript" src="https://www.google.com/jsapi"></script>
        <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
      google.charts.load(\'current\', {\'packages\':[\'corechart\']});
      google.charts.setOnLoadCallback(zobrazPieChart());</script>
      ';
	}


}
if (isset($_GET['uroven_regionu']) ) {
  $vypis = new Vypis();
  $vypis->zobrazSumar();
}

?>

<form>
	<p>nastavte parametre a kliknite na tlacidlo Zobraz Tabulku</p>
	<label for="rok" >ROK PRIJMOV</label>
	<input type="text" name="rok" value="2019"><br>
	<label for="uroven_regionu">REGIONALNA UROVEN</label>
	<input type="text" name="uroven_regionu" value="region" id="reg_level"> <button type="button" onclick="DrillDown('reg_level')">Drill Down</button> <button type="button" onclick="RollUp('reg_level')">Roll Up</button><br>

	<label for="uroven_casu">UROVNE CASU</label>
	<input type="text" name="uroven_casu" value="mesiac" id="time_level"> <button type="button" onclick="DrillDown('time_level')">Drill Down</button> <button type="button" onclick="RollUp('time_level')">Roll Up</button><br>

	<button type="submit"> Zobraz Tabulku</button>
</form>



        <!-- PivotTable.js libs from ../dist -->
        <link rel="stylesheet" type="text/css" href="../dist/pivot.css">
        <script type="text/javascript" src="../dist/pivot.js"></script>
        <script type="text/javascript" src="../dist/d3_renderers.js"></script>
        <script type="text/javascript" src="../dist/c3_renderers.js"></script>
        <script type="text/javascript" src="../dist/plotly_renderers.js"></script>
        <script type="text/javascript" src="../dist/export_renderers.js"></script>



<script type="text/javascript">
	ZobrazKontingecnuTabulky();
	function ZobrazKontingecnuTabulky () {

    google.load("visualization", "1", {packages:["corechart", "charteditor"]});
    $(function(){
        var derivers = $.pivotUtilities.derivers;
        var renderers = $.extend($.pivotUtilities.renderers,
            $.pivotUtilities.gchart_renderers);


            $("#output").pivotUI($("#input"), {
                renderers: $.extend(
      $.pivotUtilities.renderers,
      $.pivotUtilities.plotly_renderers
    ),
                derivedAttributes: {


                },
                cols: ["Age Bin"], rows: ["Gender"],
                rendererName: "Area Chart",
                rendererOptions: { gchart: {width: 800, height: 600} }
            });

     });
	}

	function zobrazPieChart(id) {
		// console.log("id je "+id);
		var tr = document.getElementById(id);
		var vsetkyHodnoty = tr.childNodes;
		var datapole = new Array();
		datapole.push(["region", "prijem v eurach"]);
		for (var i = vsetkyHodnoty.length - 1; i > 0; i--) {
			var menoRegionu = vsetkyHodnoty[i].className;
			var hodnota = parseInt(vsetkyHodnoty[i].innerText);
			console.log(menoRegionu  + ":" +hodnota);
			var jedenPrvok = [menoRegionu, hodnota];
			datapole.push(jedenPrvok);
		}

		var googlePole = [
          ['Task', 'Hours per Day'],
          ['Work',     11],
          ['Eat',      2],
          ['Commute',  2],
          ['Watch TV', 2],
          ['Sleep',    7]
        ];
        console.log("google pole (funguje) : "+ googlePole.toString());
        console.log("moje pole (nefunguje) : "+ datapole.toString());
		 var data = google.visualization.arrayToDataTable(googlePole);

		 var data = google.visualization.arrayToDataTable(datapole);

        var options = {
          title: 'Rozdelenie prijmov pre datum '+id
        };

        var chart = new google.visualization.PieChart(document.getElementById('piechart'));

        chart.draw(data, options);
	}
	function DrillDown(id){
		var input = document.getElementById(id);
		var hodnota = document.getElementById(id).value;
		if (hodnota == "region"){
			input.value = "mesto";
		}
		if (hodnota == "stat") {
			input.value = "region";
		}

		if (hodnota == "rok"){
			input.value = "mesiac";
		}
		if (hodnota == "mesiac"){
			input.value = "tyzden";
		}
		if (hodnota == "tyzden"){
			input.value = "den";
		}
	}

	function RollUp(id){
		var input = document.getElementById(id);
		var hodnota = document.getElementById(id).value;
		if (hodnota == "mesto"){
			input.value = "region";
		}
		if (hodnota == "region"){
			input.value = "stat";
		}
		if (hodnota == "den"){
			input.value = "tyzden";
		}
		if (hodnota == "tyzden"){
			input.value = "mesiac";
		}
		if (hodnota == "mesiac"){
			input.value = "rok";
		}

	}
</script>
<div id="piechart" style="width: 900px; height: 500px;"></div>
<div style="width: 900px; height: 500px;"><div id="output" ></div></div>


