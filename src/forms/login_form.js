/**
 * Import dependencies.
 */
const { __ } = wp.i18n;
import { Button, __experimentalInputControl as InputControl  } from '@wordpress/components';
import {EDLFW_ERRORS} from "../errors";

/**
 * Show login form.
 *
 * @param errors
 * @param url
 * @param setUrl
 * @param login
 * @param setLogin
 * @param password
 * @param setPassword
 * @param setEnabled
 * @returns {JSX.Element}
 * @constructor
 */
export const EDLFW_LOGIN_FORM = ( { errors, url, setUrl, login, setLogin, password, setPassword, setEnabled } ) => {
  /**
   * Handle the login itself.
   */
  function do_login() {
    // bail if one setting is not given.
    if( ! url || ! login || ! password ) {
      return;
    }

    // enable the listing.
    setEnabled( true )
  }

  return (
    <>
      <h2>{__( 'Enter your credentials' )}</h2>
      {errors && <EDLFW_ERRORS errors={errors}/>}
      <InputControl label={__( 'URL' )} value={url} onChange={(value) => setUrl( value )}/>
      <InputControl label={__( 'Login' )} value={login} onChange={(value) => setLogin( value )}/>
      <InputControl label={__( 'Password' )} type={"password"} value={password} onChange={(value) => setPassword( value )}/>
      <Button __next40pxDefaultSize variant="primary" onClick={() => do_login()}>Show directory</Button>
    </>
  )
}
