/**
 * Import dependencies.
 */
const { __ } = wp.i18n;
import {
  Button,
  __experimentalInputControl as InputControl
} from '@wordpress/components';
import {EDLFW_ERRORS} from "../errors";

/**
 * Show simple API form.
 *
 * @param errors
 * @param api_key
 * @param setEnabled
 * @returns {JSX.Element}
 * @constructor
 */
export const EDLFW_FILE_FORM = ( { errors, setEnabled, url, setUrl } ) => {
  /**
   * Handle the login itself.
   */
  function do_login() {
    // bail if one setting is not given.
    if( ! url ) {
      return;
    }

    // enable the listing.
    setEnabled( true )
  }

  return (
    <>
      <h2>{__( 'Enter the path to a local file' )}</h2>
      {errors && <EDLFW_ERRORS errors={errors}/>}
      <InputControl label={__( 'File' )} value={url} onChange={(value) => setUrl( value )}/>
      <Button variant="primary" onClick={() => do_login()}>Use file</Button>
    </>
  )
}
