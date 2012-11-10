<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: PiwikTest.php 6727 2012-08-13 20:26:46Z JulienM $
 */
class PiwikTest extends DatabaseTestCase
{
    /**
     * Dataprovider for testIsNumericValid
     */
    public function getValidNumeric()
    {
        $valid = array(
            -1, 0 , 1, 1.5, -1.5, 21111, 89898, 99999999999, -4565656,
            (float)-1, (float)0 , (float)1, (float)1.5, (float)-1.5, (float)21111, (float)89898, (float)99999999999, (float)-4565656,
            (int)-1, (int)0 , (int)1, (int)1.5, (int)-1.5, (int)21111, (int)89898, (int)99999999999, (int)-4565656,
            '-1', '0' , '1', '1.5', '-1.5', '21111', '89898', '99999999999', '-4565656',
            '1e3','0x123', "-1e-2",
        );
        foreach($valid AS $key => $value) {
            $valid[$key] = array($value);
        }
        return $valid;
    }

    /**
     * @group Core
     * @group Piwik
     * @dataProvider getValidNumeric
     */
    public function testIsNumericValid($toTest)
    {
        $this->assertTrue(is_numeric($toTest), $toTest." not valid but should!");
    }

    /**
     * Dataprovider for testIsNumericNotValid
     */
    public function getInvalidNumeric()
    {
        $notValid = array(
            '-1.0.0', '1,2',   '--1', '-.',   '- 1', '1-',
        );
        foreach($notValid AS $key => $value) {
            $notValid[$key] = array($value);
        }
        return $notValid;
    }

    /**
     * @group Core
     * @group Piwik
     * @dataProvider getInvalidNumeric
     */
    public function testIsNumericNotValid($toTest)
    {
        $this->assertFalse(is_numeric($toTest), $toTest." valid but shouldn't!");
    }

    /**
     * @group Core
     * @group Piwik
     */
    public function testSecureDiv()
    {
        $this->assertSame( 3, Piwik::secureDiv( 9,3 ) );
        $this->assertSame( 0, Piwik::secureDiv( 9,0 ) );
        $this->assertSame( 10, Piwik::secureDiv( 10,1 ) );
        $this->assertSame( 10.0, Piwik::secureDiv( 10.0, 1.0 ) );
        $this->assertSame( 5.5, Piwik::secureDiv( 11.0, 2 ) );
        $this->assertSame( 0, Piwik::secureDiv( 11.0, 'a' ) );
        
    }

    /**
     * Dataprovider for testGetPrettyTimeFromSeconds
     */
    public function getPrettyTimeFromSecondsData()
    {
        return array(
            array(30, array('30s', '00:00:30')),
            array(60, array('1 min 0s', '00:01:00')),
            array(100, array('1 min 40s', '00:01:40')),
            array(3600, array('1 hours 0 min', '01:00:00')),
            array(3700, array('1 hours 1 min', '01:01:40')),
            array(86400 + 3600 * 10, array('1 days 10 hours', '34:00:00')),
            array(86400 * 365, array('365 days 0 hours', '8760:00:00')),
            array((86400 * (365.25 + 10)), array('1 years 10 days', '9006:00:00')),
        );
    }

    /**
     * @group Core
     * @group Piwik
     * @dataProvider getPrettyTimeFromSecondsData
     */
    public function testGetPrettyTimeFromSeconds($seconds, $expected)
    {
        Piwik_Translate::getInstance()->loadEnglishTranslation();

        $sentenceExpected = str_replace(' ','&nbsp;', $expected[0]);
        $numericExpected = $expected[1];
        $this->assertEquals($sentenceExpected, Piwik::getPrettyTimeFromSeconds($seconds, $sentence = true));
        $this->assertEquals($numericExpected, Piwik::getPrettyTimeFromSeconds($seconds, $sentence = false));

        Piwik_Translate::getInstance()->unloadEnglishTranslation();
    }

    /**
     * Dataprovider for testCheckValidLoginString
     */
    public function getInvalidLoginStringData()
    {
        $notValid = array(
            '',
            '   ',
            'a',
            'aa',
            'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'alpha/beta',
            'alpha:beta',
            'alpha;beta',
            'alpha<beta',
            'alpha=beta',
            'alpha>beta',
            'alpha?beta',
        );
        foreach($notValid AS $key => $value) {
            $notValid[$key] = array($value);
        }
        return $notValid;
    }

    /**
     * @group Core
     * @group Piwik
     * @dataProvider getInvalidLoginStringData
     */
    public function testCheckInvalidLoginString($toTest)
    {
        try {
            Piwik::checkValidLoginString($toTest);
        } catch (Exception $e) {
            return;
        }
        $this->fail('Expected exception not raised');
    }

    /**
     * Dataprovider for testCheckValidLoginString
     */
    public function getValidLoginStringData()
    {
        $valid = array(
            'aaa',
            'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'shoot_puck@the-goal.com',
        );
        foreach($valid AS $key => $value) {
            $valid[$key] = array($value);
        }
        return $valid;
    }

    /**
     * @group Core
     * @group Piwik
     * @dataProvider getValidLoginStringData
     */
    public function testCheckValidLoginString($toTest)
    {
        $this->assertNull(Piwik::checkValidLoginString($toTest));
    }

	/**
	 * Dataprovider for testGetPrettyValue
	 */
	public function getGetPrettyValueTestCases()
	{
		return array(
			array('revenue', 12, '$ 12'),
			array('revenue_evolution', '100 %', '100 %'),
		);
	}

    /**
     * @group Core
     * @group Piwik
     * @dataProvider getGetPrettyValueTestCases
     */
    public function testGetPrettyValue($columnName, $value, $expected)
    {
		$access = new Piwik_Access();
		Zend_Registry::set('access', $access);
		$access->setSuperUser(true);

		$idsite = Piwik_SitesManager_API::getInstance()->addSite("test","http://test");

		$this->assertEquals(
			$expected,
			Piwik::getPrettyValue($idsite, $columnName, $value, false, false)
		);
    }
}
