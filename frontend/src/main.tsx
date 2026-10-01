import '@fontsource-variable/source-sans-3';
import '@fontsource-variable/newsreader';
import '@fontsource-variable/newsreader/wght-italic.css';
import './styles/tokens.css';
import './styles/base.css';
import './components/ui/ui.css';
import './styles/shell.css';

import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { App } from './App';

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
