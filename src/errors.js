/**
 * Import dependencies.
 */
const { _n } = wp.i18n;

/**
 * Show list of errors.
 *
 * @param errors
 * @constructor
 */
export const EDLFW_ERRORS = ( { errors } ) => {
  return (
    <>
      <div className="errors">
        <p><strong>{_n( 'The following error occurred:', 'The following errors occurred:', errors.length )}</strong></p>
        <ul>
        {errors.map( error => {
          return <li>{error}</li>
        })
        }
        </ul>
      </div>
    </>
  )
}
