<?php
/**
 * Created by PhpStorm.
 * User: zakhar
 * Date: 18-3-19
 * Time: 14:50
 */

namespace ReVival\Test;

require dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR .'autoload.php';
include_once '../algebra.php';
include_once '../connect.php';

use PHPUnit\Framework\TestCase;
use ReVival\utils;
use \InvalidArgumentException;

class RecoveryTest extends TestCase {

    private function generateFullSample()
    {
        $sample = new \stdClass();

        $sample->{"series"} = array();

        // serie 0
        $serie = array();
        $serie["id"] = "2112";
        $serie["title"] = "Appenzell";
        $points = array(
            array(1104192000000, -0.970024348480708),
            array(1104278400000, -1.0390951622447462),
            array(1104364800000, -1.0731777973930834),
            array(1104451200000, -1.1700541214821698),
            array(1104537600000, -1.1302376519631638),
            array(1104624000000, -0.8396657660450441),
            array(1104710400000, 0.5077592642434499),
            array(1104796800000, -0.02862998461774148),
            array(1104883200000, -0.5740505904912068),
            array(1104969600000, -0.7670162250855939),
            array(1105056000000, 0.8432560511178603),
            array(1105142400000, 0.31312436066999294),
            array(1105228800000, -0.2879496629515356),
            array(1105315200000, -0.4185465521278252),
            array(1105401600000, -0.49510737221894446),
        );
        $serie["points"] = $points;
        $sample->{"series"}[] = $serie;

        // serie 1
        $serie = array();
        $serie["id"] = "2181";
        $serie["title"] = "Halden";
        $points = array(
            array(1104192000000, -0.5860302173365539),
            array(1104278400000, -0.6546871709748043),
            array(1104364800000, -0.7476142728492786),
            array(1104451200000, -0.8277057188476894),
            array(1104537600000, -0.8766652693868876),
            array(1104624000000, -0.79009253119228),
            array(1104710400000, 0.34635196943776825),
            array(1104796800000, 0.6263253539732766),
            array(1104883200000, -0.14713534407953677),
            array(1104969600000, -0.39232196145957837),
            array(1105056000000, 0.431762253104055),
            array(1105142400000, 0.6185324644428621),
            array(1105228800000, -0.12666665946052252),
            array(1105315200000, -0.2886974183745343),
            array(1105401600000, -0.32586027488110425),
        );
        $serie["points"] = $points;
        $sample->{"series"}[] = $serie;

        // serie 2
        $serie = array();
        $serie["id"] = "2303";
        $serie["title"] = "Jonschwil";
        $points = array(
            array(1104188400000, -0.7473622959737329),
            array(1104274800000, -0.8715818677334112),
            array(1104361200000, -0.9041114895087249),
            array(1104447600000, -1.0163853773711813),
            array(1104534000000, -1.0654868035468141),
            array(1104620400000, -0.8654989703830036),
            array(1104706800000, 0.18635139546990367),
            array(1104793200000, 0.2538772533497085),
            array(1104879600000, -0.30312405000744747),
            array(1104966000000, -0.5499681874878986),
            array(1105052400000, 0.7224575494821498),
            array(1105138800000, 0.5138147304741421),
            array(1105225200000, -0.10089547224734137),
            array(1105311600000, -0.2961287732913652),
            array(1105398000000, -0.3711266751713576),
        );
        $serie["points"] = $points;
        $sample->{"series"}[] = $serie;

        ////////////

        return $sample;
    }

    private function generatePartialSample()
    {
        $sample = new \stdClass();

        $sample->{"series"} = array();

        // serie 0
        $serie = array();
        $serie["id"] = "2112";
        $serie["title"] = "Appenzell";
        $points = array(
            array(1104192000000, -0.970024348480708),
            array(1104278400000, -1.0390951622447462),
            array(1104364800000, null),
            array(1104451200000, null),
            array(1104537600000, null),
            array(1104624000000, -0.8396657660450441),
            array(1104710400000, 0.5077592642434499),
            array(1104796800000, -0.02862998461774148),
            array(1104883200000, -0.5740505904912068),
            array(1104969600000, -0.7670162250855939),
            array(1105056000000, 0.8432560511178603),
            array(1105142400000, 0.31312436066999294),
            array(1105228800000, -0.2879496629515356),
            array(1105315200000, -0.4185465521278252),
            array(1105401600000, -0.49510737221894446),
        );
        $ground = array(
            array(1104192000000, -0.970024348480708),
            array(1104278400000, -1.0390951622447462),
            array(1104364800000, -1.0731777973930834),
            array(1104451200000, -1.1700541214821698),
            array(1104537600000, -1.1302376519631638),
            array(1104624000000, -0.8396657660450441),
            array(1104710400000, 0.5077592642434499),
            array(1104796800000, -0.02862998461774148),
            array(1104883200000, -0.5740505904912068),
            array(1104969600000, -0.7670162250855939),
            array(1105056000000, 0.8432560511178603),
            array(1105142400000, 0.31312436066999294),
            array(1105228800000, -0.2879496629515356),
            array(1105315200000, -0.4185465521278252),
            array(1105401600000, -0.49510737221894446),
        );
        $serie["points"] = $points;
        $serie["ground"] = $ground;
        $sample->{"series"}[] = $serie;

        // serie 1
        $serie = array();
        $serie["id"] = "2181";
        $serie["title"] = "Halden";
        $points = array(
            array(1104192000000, -0.5860302173365539),
            array(1104278400000, -0.6546871709748043),
            array(1104364800000, -0.7476142728492786),
            array(1104451200000, -0.8277057188476894),
            array(1104537600000, -0.8766652693868876),
            array(1104624000000, -0.79009253119228),
            array(1104710400000, 0.34635196943776825),
            array(1104796800000, 0.6263253539732766),
            array(1104883200000, -0.14713534407953677),
            array(1104969600000, -0.39232196145957837),
            array(1105056000000, 0.431762253104055),
            array(1105142400000, 0.6185324644428621),
            array(1105228800000, -0.12666665946052252),
            array(1105315200000, -0.2886974183745343),
            array(1105401600000, -0.32586027488110425),
        );
        $serie["points"] = $points;
        $sample->{"series"}[] = $serie;

        // serie 2
        $serie = array();
        $serie["id"] = "2303";
        $serie["title"] = "Jonschwil";
        $points = array(
            array(1104188400000, -0.7473622959737329),
            array(1104274800000, -0.8715818677334112),
            array(1104361200000, -0.9041114895087249),
            array(1104447600000, -1.0163853773711813),
            array(1104534000000, -1.0654868035468141),
            array(1104620400000, -0.8654989703830036),
            array(1104706800000, 0.18635139546990367),
            array(1104793200000, 0.2538772533497085),
            array(1104879600000, -0.30312405000744747),
            array(1104966000000, -0.5499681874878986),
            array(1105052400000, null),
            array(1105138800000, null),
            array(1105225200000, null),
            array(1105311600000, -0.2961287732913652),
            array(1105398000000, -0.3711266751713576),
        );
        $ground = array(
            array(1104188400000, -0.7473622959737329),
            array(1104274800000, -0.8715818677334112),
            array(1104361200000, -0.9041114895087249),
            array(1104447600000, -1.0163853773711813),
            array(1104534000000, -1.0654868035468141),
            array(1104620400000, -0.8654989703830036),
            array(1104706800000, 0.18635139546990367),
            array(1104793200000, 0.2538772533497085),
            array(1104879600000, -0.30312405000744747),
            array(1104966000000, -0.5499681874878986),
            array(1105052400000, 0.7224575494821498),
            array(1105138800000, 0.5138147304741421),
            array(1105225200000, -0.10089547224734137),
            array(1105311600000, -0.2961287732913652),
            array(1105398000000, -0.3711266751713576),
        );
        $serie["points"] = $points;
        $serie["ground"] = $ground;
        $sample->{"series"}[] = $serie;

        ////////////

        return $sample;
    }

    public function testRecovery() {
        $visibility = array();

        $vis_curr = new \stdClass();
        $vis_curr->{"id"} = "2112";
        $vis_curr->{"visible"} = true;
        $visibility[] = $vis_curr;

        $vis_curr = new \stdClass();
        $vis_curr->{"id"} = "2181";
        $vis_curr->{"visible"} = true;
        $visibility[] = $vis_curr;

        $vis_curr = new \stdClass();
        $vis_curr->{"id"} = "2303";
        $vis_curr->{"visible"} = true;
        //$visibility[] = $vis_curr;

        $sample = $this->generatePartialSample();
        $retval = recover_all(null, $sample, 0.0001, 0, "hourly", $visibility);
        $this->assertTrue(count($retval->{"series"}[0]["recovered"]) == 15);
        $this->assertTrue(count($retval->{"series"}[2]["recovered"]) == 15);

        //var_dump($retval);
        //var_dump($retval->{"series"});
        //var_dump($retval->{"series"}[0]);
        //var_dump($retval->{"series"}[2]);
    }

    public function testRecoveryC($conn) {
        $visibility = array();

        $vis_curr = new \stdClass();
        $vis_curr->{"id"} = "2112";
        $vis_curr->{"visible"} = true;
        $visibility[] = $vis_curr;

        $vis_curr = new \stdClass();
        $vis_curr->{"id"} = "2181";
        $vis_curr->{"visible"} = true;
        $visibility[] = $vis_curr;

        $vis_curr = new \stdClass();
        $vis_curr->{"id"} = "2303";
        $vis_curr->{"visible"} = true;
        //$visibility[] = $vis_curr;

        $sample = $this->generatePartialSample();
        $retval = recover_all($conn, $sample, 0.0001, 0, "hourly", $visibility);
        $this->assertTrue(count($retval->{"series"}[0]["recovered"]) == 15);
        $this->assertTrue(count($retval->{"series"}[2]["recovered"]) == 15);

        //var_dump($retval);
        //var_dump($retval->{"series"});
        //var_dump($retval->{"series"}[0]);
        //var_dump($retval->{"series"}[2]);
    }
}
//(new RecoveryTest())->testRecoveryC($conn);
//(new RecoveryTest())->testRecovery();
?>