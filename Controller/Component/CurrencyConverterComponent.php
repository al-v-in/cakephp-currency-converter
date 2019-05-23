<?php
App::uses('Component', 'Controller', 'Session');

class CurrencyConverterComponent extends Component {
    var $controller = '';
    var $components = array('RequestHandler');

    function initialize(Controller $controller, $settings = array()) {
        $this->controller =& $controller;
    }

    public function convert($fromCurrency, $toCurrency, $amount, $saveIntoDb = 1, $hourDifference = 1) {
        $rate = $this->getRate($fromCurrency, $toCurrency, $saveIntoDb, $hourDifference);
        return $rate * $amount;
    }
    
    public function getRate($fromCurrency, $toCurrency, $saveIntoDb = 1, $hourDifference = 1) {
        $rate = 0;
        
        if($fromCurrency != $toCurrency){
            $isFound = false;
            
            if ($fromCurrency=="PDS")
                $fromCurrency = "GBP";
            
            if($saveIntoDb == 1){
                $this->checkIfExistTable();
                
                $CurrencyConverter = ClassRegistry::init('CurrencyConverter');
                $result = $CurrencyConverter->find('all', array('conditions' => array('from' => $fromCurrency, 'to' => $toCurrency)));
                
                foreach ($result as $row){
                    $isFound = true;
                    $last_updated = $row['CurrencyConverter']['modified'];
                    $now = date('Y-m-d H:i:s');
                    $d_start = new DateTime($now);
                    $d_end = new DateTime($last_updated);
                    $diff = $d_start->diff($d_end);
                    
                    if(((int)$diff->y >= 1) || ((int)$diff->m >= 1) || ((int)$diff->d >= 1) || ((int)$diff->h >= $hourDifference) || ((double)$row['CurrencyConverter']['rates'] == 0)){
                        $rate = $this->getRemoteRate($fromCurrency, $toCurrency);
                        
                        $CurrencyConverter->id = $row['CurrencyConverter']['id'];
                        $CurrencyConverter->set(array(
                            'from' => $fromCurrency,
                            'to' => $toCurrency,
                            'rates' => $rate,
                            'modified' => date('Y-m-d H:i:s'),
                        ));
                        
                        $CurrencyConverter->save();
                    }
                    else
                        $rate = $row['CurrencyConverter']['rates'];
                }
                
                if(!$isFound){
                    $rate = $this->getRemoteRate($fromCurrency, $toCurrency);
                    
                    $CurrencyConverter->create();
                    $CurrencyConverter->set(array(
                        'from' => $fromCurrency,
                        'to' => $toCurrency,
                        'rates' => $rate,
                        'created' => date('Y-m-d H:i:s'),
                        'modified' => date('Y-m-d H:i:s'),
                    ));
                    
                    $CurrencyConverter->save();
                }
            }
            else{
                $rate = $this->getRemoteRate($fromCurrency, $toCurrency);
            }
        }
    
        return $rate;
    }
    
    protected function getRemoteRate($fromCurrency, $toCurrency){
        $url = 'https://api.exchangeratesapi.io/latest?base=' . $fromCurrency . '&symbols=' . $toCurrency;
        $handle = @fopen($url, 'r');
         
        if ($handle) {
            $result = fgets($handle, 4096);
            fclose($handle);
        }

        if(isset($result)){
            $conversion = json_decode($result, true);
            if (isset($conversion['rates'][$toCurrency])) {
                $rate = $conversion['rates'][$toCurrency];
            }
            else
                $rate = 0;
        }
        else
            $rate = 0;

        return($rate);
    }

    private function checkIfExistTable(){
        $find = 0;
        App::uses('ConnectionManager', 'Model');
        $db = ConnectionManager::getDataSource('default');
        $tables = $db->listSources();
        
        foreach($tables as $t){
            if($t == 'currency_converters')
                $find = 1;
        }

        if($find == 0){
            $sql = 'CREATE TABLE IF NOT EXISTS `currency_converters` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `from` varchar(5) NOT NULL,
              `to` varchar(5) NOT NULL,
              `rates` varchar(10) NOT NULL,
              `created` datetime NOT NULL,
              `modified` datetime NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;';

            $results = $db->query($sql);
        }
        else
            return(true);
    }
}
