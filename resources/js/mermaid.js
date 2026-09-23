import mermaid from 'mermaid';

mermaid.initialize({ startOnLoad: false, securityLevel: 'strict' });
window.mermaid = mermaid;
document.dispatchEvent(new Event('mermaid:ready'));
