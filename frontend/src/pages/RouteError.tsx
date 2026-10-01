import { useEffect } from 'react';
import { useRouteError } from 'react-router';
import { MessagePage } from './NotFoundPage';

/**
 * Unexpected rendering or module loading error. It is logged to the console
 * for debugging, but the person only sees a clear message.
 */
export function RouteError() {
  const error = useRouteError();

  useEffect(() => {
    console.error(error);
  }, [error]);

  const chunkFailed = error instanceof Error && /dynamically imported module|Loading chunk/i.test(error.message);

  return (
    <MessagePage
      code="Oops"
      title={chunkFailed ? 'A new version is available' : 'Something did not go as expected'}
      text={
        chunkFailed
          ? 'Reload the page to continue with the latest version.'
          : 'You have not lost anything that was already saved. Reload the page or go back to home to try again.'
      }
    />
  );
}
