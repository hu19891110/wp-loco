<?php
/**
 * Abstracts session data access using WP_Session_Tokens
 */
class Loco_data_Session extends Loco_data_Serializable {
    
    /**
     * @var Loco_data_Session
     */
    private static $instance;
    
    /**
     * Value from wp_get_session_token
     * @var string
     */
    private $token;
    
    /**
     * @var WP_User_Meta_Session_Tokens
     */
    private $manager;

    /**
     * Dirty flag: TODO abstract into array access setters
     * @var bool
     */
    private $dirty = false;


    /**
     * @return Loco_data_Session
     */
    public static function get(){
        if( ! self::$instance ){
            new Loco_data_Session;
        }
        return self::$instance;
    }



    /**
     * Trash data and remove from memory
     */
    public static function destroy(){
        if( self::$instance ){
            try {
                self::$instance->clear();
            }
            catch( Exception $e ){
                // probably no session to destroy
            }
            self::$instance = null;
        }
    }



    /**
     * Commit current session data to WordPress storage and remove from memory
     */
    public static function close(){
        if( self::$instance && self::$instance->dirty ){
            self::$instance->persist();
            self::$instance = null;
        }
    } 



    /**
     * @internal
     */
    final public function __construct( array $raw = array() ){
        parent::__construct( array() );
        $this->token = wp_get_session_token();
        $this->manager = WP_Session_Tokens::get_instance( get_current_user_id() );
        // populate object from stored session data
        $data = $this->getRaw();
        if( isset($data['loco']) ){
            $this->setUnserialized( $data['loco'] );
        }
        // any initial arbitrary data can be merged on top
        foreach( $raw as $prop => $value ){
            $this[$prop] = $value;
        }
        // enforce single instance
        self::$instance = $this;
        // ensure against unclean shutdown
        if( loco_debugging() ){
            register_shutdown_function( array($this,'_on_shutdown') );
        }
    }


    /**
     * @internal
     * Ensure against unclean use of session storage
     */
    public function _on_shutdown(){
        if( $this->dirty ){
            trigger_error('Unclean session shutdown: call either Loco_data_Session::destroy or Loco_data_Session::close');
        }
    }


    /**
     * Get raw session data held by WordPress
     * @return array
     */
    private function getRaw(){
        $data = $this->manager->get( $this->token );
        // session data will exist if WordPress login is valid
        if( ! $data || ! is_array($data) ){
            throw new Loco_error_Exception('Invalid session');
        }

        return $data;
    }



    /**
     * Persist object in WordPress usermeta table
     * @return Loco_data_Session
     */
    public function persist(){
        $data = $this->getRaw();
        $data['loco'] = $this->getSerializable();
        $this->manager->update( $this->token, $data );
        $this->dirty = false;
        return $this;
    }



    /**
     * Clear object data and remove our key from WordPress usermeta record
     * @return Loco_data_Session
     */
    public function clear(){
        $data = $this->getRaw();
        if( isset($data['loco']) ){
            unset( $data['loco'] );
            $this->manager->update( $this->token, $data );
        }
        $this->exchangeArray( array() );
        $this->dirty = false;
        return $this;
    }


    /**
     * @param string name of messages bag, e.g. "errors"
     * @param mixed optionally put data in rather than getting data out
     * @return mixed
     */
    public function flash( $bag, $data = null ){
        if( isset($data) ){
            $this->dirty = true;
            $this[$bag][] = $data;
            return;
        }
        // else get first object in bag and remove before returning
        if( isset($this[$bag]) ){
            if( $data = array_shift($this[$bag]) ){
                $this->dirty = true;
                return $data;
            }
        }
    }    



    /**
     * @internal
     */
    public function offsetSet( $index, $newval ){
        if( ! isset($this[$index]) || $newval !== $this[$index] ){
            $this->dirty = true;
            parent::offsetSet( $index, $newval );
        }
    }



    /**
     * @internal
     */
    public function offsetUnset( $index ){
        if( isset($this[$index]) ){
            $this->dirty = true;
            parent::offsetUnset( $index );
        }
    }
    
}
