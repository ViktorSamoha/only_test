<?php

use \Bitrix\Main\Loader;
use \Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\ErrorCollection;
use Bitrix\Main\Engine\Response\AjaxJson;
use Bitrix\Highloadblock as HL;
use Bitrix\Main\Entity;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
class CarSelect extends CBitrixComponent implements Controllerable
{
    public function configureActions()
    {
        return [
            'getCars' => [
                'prefilters' => []
            ]
        ];
    }

    public function getVehiclesList($id){
        if (Loader::includeModule("iblock")) {
            $arResult = [];
            $arSelect = ['ID', 'NAME', 'PROPERTY_COMFORT_CATEGORY', 'PROPERTY_DRIVER_ID'];
            $arFilter = ['IBLOCK_ID' => ID, 'GLOBAL_ACTIVE' => 'Y', 'ID'=>$id];
            $res = CIBlockElement::GetList([], $arFilter, false, [], $arSelect);
            while ($ob = $res->GetNextElement()) {
                $arFields = $ob->GetFields();
                $arResult[] = [
                    'ID'=>$arFields['ID'],
                    'NAME'=>$arFields['NAME'],
                    'COMFORT_CATEGORY'=>$arFields['PROPERTY_COMFORT_CATEGORY_VALUE'],
                    'DRIVER'=>$arFields['PROPERTY_DRIVER_ID'],
                ];
            }
            if(!empty($arResult)){
                return $arResult;
            }else{
                return false;
            }
        } else {
            return false;
        }
    }

    public function checkVehicleTime($startTime, $endTime, $arId){
        if($startTime && $endTime && $arId){
            if(Loader::includeModule("highloadblock")){
                $arUnavailableVehicles = [];
                $hlblock = HL\HighloadBlockTable::getById(ID)->fetch();
                $entity = HL\HighloadBlockTable::compileEntity($hlblock);
                $entity_data_class = $entity->getDataClass();
                $data = $entity_data_class::getList(array(
                    "select" => array("UF_VEHICLE_ID"),
                    "order" => array("ID" => "DESC"),
                    "filter" => array("UF_START_TIME" => $startTime, "UF_END_TIME" => $endTime, 'UF_VEHICLE_ID'=>$arId),
                ));
                while ($arData = $data->Fetch()) {
                    $arUnavailableVehicles[] = $arData['UF_VEHICLE_ID'];
                }

                if(!empty($arUnavailableVehicles)){
                    return array_diff($arId, $arUnavailableVehicles);
                }else{
                    return $arId;
                }

            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    public function getUserAvailableVehiclesComfortClass($work_position){
        if($work_position){
            if(Loader::includeModule("highloadblock")){
                $arResult = [];
                $hlblock = HL\HighloadBlockTable::getById(ID)->fetch();
                $entity = HL\HighloadBlockTable::compileEntity($hlblock);
                $entity_data_class = $entity->getDataClass();
                $data = $entity_data_class::getList(array(
                    "select" => array("UF_COMFORT_CATEGORY"),
                    "order" => array("ID" => "DESC"),
                    "filter" => array("UF_WORK_POSITION" => $work_position)
                ));
                while ($arData = $data->Fetch()) {
                    $arResult[] = $arData['UF_COMFORT_CATEGORY'];
                }
                if(!empty($arResult)){
                    return $arResult;
                }else{
                    return false;
                }
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    public function getAvailableVehicles($arComfortClass){
        if($arComfortClass){
            if (Loader::includeModule("iblock")) {
                $arResult = [];
                $arSelect = ['ID', 'NAME'];
                $arFilter = ['IBLOCK_ID' => ID, 'GLOBAL_ACTIVE' => 'Y', 'PROPERTY_COMFORT_CLASS' => $arComfortClass];
                $res = CIBlockElement::GetList([], $arFilter, false, [], $arSelect);
                while ($ob = $res->GetNextElement()) {
                    $arFields = $ob->GetFields();
                    $arResult[] = $arFields['ID'];
                }
                if(!empty($arResult)){
                    return $arResult;
                }else{
                    return false;
                }
            } else {
                return false;
            }
        }else{
            return false;
        }
    }

    /*
     * Возвращает перечень доступных автомобилей
     * */
    public function getCars(): AjaxJson
    {
        $get = $this->request->getQueryList()->toArray();
        if (isset($get['start_time']) && isset($get['end_time']) && isset($get['user_id'])) {

            //получаем данные сотрудника
            $rsUser = CUser::GetByID($get['user_id']);
            $arUser = $rsUser->Fetch();

            //получаем категории автомобилей доступных для сотрудника
            $arComfortClass = $this->getUserAvailableVehiclesComfortClass($arUser['WORK_POSITION']);
            if($arComfortClass){
                //получаем список доступных автомобилей
                $arVehicles = $this->getAvailableVehicles($arComfortClass);
                if($arVehicles){
                    //филтруем список автомобилей по времени
                    $arAvailableVehicles = $this->checkVehicleTime($get['start_time'], $get['end_time'], $arVehicles);
                    if($arAvailableVehicles){
                        //формируем конечный массив данных с необходимыми параметрами
                        $result = $this->getVehiclesList($arAvailableVehicles);
                        return AjaxJson::createSuccess($result);
                    }else{
                        return AjaxJson::createError(null, 'нет значений');
                    }
                }else{
                    return AjaxJson::createError(null, 'нет значений');
                }
            }else{
                return AjaxJson::createError(null, 'нет значений');
            }
        } else {
            return AjaxJson::createError(null, 'нет значений');
        }
    }

    public function executeComponent()
    {
        $this->IncludeComponentTemplate($this->componentPage);
    }

}

