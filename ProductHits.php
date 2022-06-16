<?
class ProductHits {
    public $IBLOCK_ID = 21;
    public $popularSection = 649;
    public $popularProps = 82;
    public $month;
    public $countPopular = 8; // больше значит популярное

    public function __construct() {
        CModule::IncludeModule("iblock");
        $this->month = time() - 3600 * 24 * 30;
    }

    public function getSales() {
        global $DB;
        $arFilter = Array(
            ">=DATE_INSERT" => date(
                $DB->DateFormatToPHP(CSite::GetDateFormat("SHORT")), $this->month
            )
        );

        $db_sales = CSaleOrder::GetList(
            array("DATE_INSERT" => "ASC"),
            $arFilter

        );
        $arOrders = array();
        while ($ar_sales = $db_sales->Fetch()) {
            $arOrders[] = $ar_sales['ID'];
        }
        return $arOrders;
    }
    public function getHits() {
        $arOrders = $this->getSales();
        $arOrderProduct = array();
        foreach ($arOrders as $key => $order_id) {
            $dbBasketItems = CSaleBasket::GetList(array(), array("ORDER_ID" => $order_id), false, false, array());
            $product = array();
            while ($arItems = $dbBasketItems->Fetch()) {
                $arOrderProduct[$arItems['PRODUCT_ID']][] = 'Y';
            }
            unset($dbBasketItems);
        }
        $arHits = array();
        foreach ($arOrderProduct as $productID => $arProduct) {
            if (count($arProduct) > $this->countPopular) {
                $arHits[] = $productID;
            }
        }
        return $arHits;
    }
    public function getFields($arr = false, $section = false) {
        $result = [];
        $el = new CIBlockElement();
        $filter['IBLOCK_ID'] = $this->IBLOCK_ID;
        $filter['ACTIVE'] = 'Y';
        if($section)
            $filter['SECTION_ID'] = $section;
        if($arr && $arr != [])
            $filter['ID'] = $arr;

        $ob = $el->GetList([], $filter, false, false, ['ID', 'NAME', 'IBLOCK_ID', 'IBLOCK_SECTION_ID', 'DATE_CREATE', 'PROPERTY_HIT']);
        while ($res = $ob->GetNextElement()) {
            $arFields = $res->GetFields();
            $result[$arFields['ID']]['IBLOCK_SECTION_ID'] = $arFields['IBLOCK_SECTION_ID'];
            $arProps = $res->GetProperties();
            $result[$arFields['ID']]['HIT'] = $arProps['HIT']['VALUE_ENUM_ID'];
            $db_groups = CIBlockElement::GetElementGroups($arFields['ID'], true);
            while($ar_group = $db_groups->Fetch()) {
                $result[$arFields['ID']]['IBLOCK_SECTION'][] = $ar_group['ID'];
            }
        }
        return $result;
    }
    private function setSection($id, $arField) {
        $el = new CIBlockElement();
        $arrayFilds = [
            "IBLOCK_SECTION_ID" => $arField['IBLOCK_SECTION_ID'],
            "IBLOCK_SECTION" => $arField['IBLOCK_SECTION'],
        ];
        $res = $el->Update($id, $arrayFilds);
        if(!$arField['HIT'])
            $arField['HIT'] = false;
        $el->SetPropertyValuesEx($id, false, array('HIT' => $arField['HIT']));
        return $res;

    }
    public function addPopular() {
        $arHits = $this->getHits();
        $arFields = $this->getFields($arHits);
        foreach ($arFields as $ID => $arField) {
            $arField['IBLOCK_SECTION'][] = $this->popularSection;
            $arField['HIT'][] = $this->popularProps;
            $res = $this->setSection($ID, $arField);
        }
    }
    public function removePopular() {
        $arFields = $this->getFields('', $this->popularSection);
        foreach ($arFields as $ID => $arField) {
            $arField['IBLOCK_SECTION'] = \array_diff($arField['IBLOCK_SECTION'], [$this->popularSection]);
            $arField['HIT'] = \array_diff($arField['HIT'], [$this->popularProps]);
            $res = $this->setSection($ID, $arField);
        }
    }
}
