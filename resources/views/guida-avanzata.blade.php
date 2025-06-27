@extends('layouts.app')

@push('styles')
<style>
.markdown-content {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    line-height: 1.6;
    color: #e9ecef;
}

.markdown-content h1 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    margin-top: 2rem;
    color: #ffffff;
    border-bottom: 3px solid #0d6efd;
    padding-bottom: 0.5rem;
}

.markdown-content h2 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-top: 2rem;
    margin-bottom: 1rem;
    color: #ffffff;
    border-bottom: 2px solid #6c757d;
    padding-bottom: 0.25rem;
}

.markdown-content h3 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    color: #ffc107;
}

.markdown-content h4 {
    font-size: 1.1rem;
    font-weight: 600;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
    color: #20c997;
}

.markdown-content p {
    margin-bottom: 1rem;
    color: #e9ecef;
}

.markdown-content ul, .markdown-content ol {
    margin-bottom: 1rem;
    padding-left: 1.5rem;
}

.markdown-content li {
    margin-bottom: 0.25rem;
    color: #e9ecef;
}

.markdown-content strong {
    color: #ffffff;
    font-weight: 600;
}

.markdown-content em {
    color: #ffc107;
    font-style: italic;
}

/* Syntax Highlighting per Code Blocks */
.markdown-content pre {
    background-color: #1e1e1e;
    border: 1px solid #444;
    border-radius: 8px;
    padding: 1rem;
    margin: 1rem 0;
    overflow-x: auto;
    position: relative;
}

.markdown-content pre code {
    background: none;
    border: none;
    padding: 0;
    color: #d4d4d4;
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 0.9rem;
    line-height: 1.4;
}

.markdown-content code {
    background-color: #2d2d2d;
    color: #f8f8f2;
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 0.85rem;
    border: 1px solid #444;
}

/* PHP Syntax Highlighting */
.markdown-content pre code {
    color: #d4d4d4; /* Default text */
}

/* Syntax highlighting colors */
.markdown-content pre code {
    /* Keywords in blue */
    background: linear-gradient(transparent, transparent);
}

/* Blockquotes */
.markdown-content blockquote {
    border-left: 4px solid #0d6efd;
    padding-left: 1rem;
    margin: 1rem 0;
    background-color: rgba(13, 110, 253, 0.1);
    padding: 0.5rem 1rem;
    border-radius: 0 4px 4px 0;
}

.markdown-content blockquote p {
    margin-bottom: 0;
    color: #b3d7ff;
}

/* Tables */
.markdown-content table {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
    background-color: #2d2d2d;
    border-radius: 8px;
    overflow: hidden;
}

.markdown-content th,
.markdown-content td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #444;
}

.markdown-content th {
    background-color: #0d6efd;
    color: #ffffff;
    font-weight: 600;
}

.markdown-content tr:hover {
    background-color: rgba(255, 255, 255, 0.05);
}

/* Links */
.markdown-content a {
    color: #0d6efd;
    text-decoration: none;
}

.markdown-content a:hover {
    color: #6ea8fe;
    text-decoration: underline;
}

/* Horizontal Rules */
.markdown-content hr {
    border: none;
    height: 2px;
    background: linear-gradient(90deg, transparent, #6c757d, transparent);
    margin: 2rem 0;
}

/* Code language indicators - simplified */
.markdown-content pre {
    position: relative;
}

.markdown-content pre::after {
    content: "CODE";
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    background-color: #0d6efd;
    color: white;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    opacity: 0.8;
}

/* Responsive */
@media (max-width: 768px) {
    .markdown-content {
        font-size: 0.9rem;
    }

    .markdown-content h1 {
        font-size: 1.5rem;
    }

    .markdown-content h2 {
        font-size: 1.25rem;
    }

    .markdown-content pre {
        padding: 0.75rem;
        font-size: 0.8rem;
    }
}

/* Scrollbar per code blocks */
.markdown-content pre::-webkit-scrollbar {
    height: 8px;
}

.markdown-content pre::-webkit-scrollbar-track {
    background: #2d2d2d;
}

.markdown-content pre::-webkit-scrollbar-thumb {
    background: #6c757d;
    border-radius: 4px;
}

.markdown-content pre::-webkit-scrollbar-thumb:hover {
    background: #adb5bd;
}
</style>
@endpush

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-11">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>{{ __('custom.Guida Avanzata') }}</span>
                        <a href="{{ route('documentazione') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>Torna alla Documentazione
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="markdown-content">
                        {!! Illuminate\Support\Str::markdown(file_get_contents(base_path('GUIDA_AVANZATA.md'))) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Aggiungi syntax highlighting dinamico
    const codeBlocks = document.querySelectorAll('.markdown-content pre code');

    codeBlocks.forEach(function(block) {
        const code = block.textContent;
        let language = 'CODE';

        // Rileva il linguaggio
        if (code.indexOf('<?php') !== -1 || code.indexOf('class ') !== -1 || code.indexOf('function ') !== -1 || code.indexOf('public ') !== -1) {
            language = 'PHP';
            highlightPHP(block);
        } else if (code.indexOf('CREATE TABLE') !== -1 || code.indexOf('SELECT') !== -1 || code.indexOf('INSERT') !== -1 || code.indexOf('UPDATE') !== -1) {
            language = 'SQL';
            highlightSQL(block);
        } else if (code.indexOf('export default') !== -1 || code.indexOf('function(') !== -1 || code.indexOf('const ') !== -1 || code.indexOf('let ') !== -1) {
            language = 'JS';
            highlightJS(block);
        } else if (code.indexOf('#!/bin/bash') !== -1 || code.indexOf('git ') !== -1 || code.indexOf('echo ') !== -1) {
            language = 'BASH';
            highlightBash(block);
        }

        // Aggiorna il label del linguaggio
        const pre = block.parentElement;
        if (pre.tagName === 'PRE') {
            const existingLabel = pre.querySelector('::after');
            pre.style.setProperty('--lang-label', `"${language}"`);
            pre.style.setProperty('content', `var(--lang-label)`, 'important');
        }
    });

    function highlightPHP(block) {
        let html = block.innerHTML;

        // Keywords PHP
        const phpKeywords = ['class', 'function', 'public', 'private', 'protected', 'static', 'return', 'if', 'else', 'foreach', 'while', 'for', 'try', 'catch', 'throw', 'new', 'extends', 'implements', 'use', 'namespace'];
        phpKeywords.forEach(keyword => {
            const regex = new RegExp(`\\b${keyword}\\b`, 'g');
            html = html.replace(regex, `<span style="color: #569cd6;">${keyword}</span>`);
        });

        // Strings
        html = html.replace(/(["'])((?:\\.|(?!\1)[^\\])*?)\1/g, '<span style="color: #ce9178;">$1$2$1</span>');

        // Comments
        html = html.replace(/\/\*[\s\S]*?\*\//g, '<span style="color: #6a9955;">$&</span>');
        html = html.replace(/\/\/.*$/gm, '<span style="color: #6a9955;">$&</span>');

        // Variables
        html = html.replace(/\$\w+/g, '<span style="color: #9cdcfe;">$&</span>');

        // Functions
        html = html.replace(/(\w+)(\s*\()/g, '<span style="color: #dcdcaa;">$1</span>$2');

        block.innerHTML = html;
    }

    function highlightSQL(block) {
        let html = block.innerHTML;

        // SQL Keywords
        const sqlKeywords = ['SELECT', 'FROM', 'WHERE', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'TABLE', 'INDEX', 'ALTER', 'DROP', 'JOIN', 'INNER', 'LEFT', 'RIGHT', 'ON', 'GROUP', 'BY', 'ORDER', 'HAVING', 'UNION', 'AND', 'OR', 'NOT', 'NULL', 'PRIMARY', 'KEY', 'FOREIGN', 'REFERENCES', 'UNIQUE', 'AUTO_INCREMENT', 'DEFAULT', 'TIMESTAMP', 'VARCHAR', 'INT', 'BIGINT', 'TEXT', 'BOOLEAN', 'ENUM'];
        sqlKeywords.forEach(keyword => {
            const regex = new RegExp(`\\b${keyword}\\b`, 'gi');
            html = html.replace(regex, `<span style="color: #569cd6;">${keyword.toUpperCase()}</span>`);
        });

        // Strings
        html = html.replace(/(["'])((?:\\.|(?!\1)[^\\])*?)\1/g, '<span style="color: #ce9178;">$1$2$1</span>');

        // Comments
        html = html.replace(/--.*$/gm, '<span style="color: #6a9955;">$&</span>');

        block.innerHTML = html;
    }

    function highlightJS(block) {
        let html = block.innerHTML;

        // JS Keywords
        const jsKeywords = ['function', 'const', 'let', 'var', 'if', 'else', 'for', 'while', 'return', 'export', 'import', 'default', 'class', 'extends', 'constructor', 'async', 'await', 'try', 'catch', 'throw', 'new'];
        jsKeywords.forEach(keyword => {
            const regex = new RegExp(`\\b${keyword}\\b`, 'g');
            html = html.replace(regex, `<span style="color: #569cd6;">${keyword}</span>`);
        });

        // Strings
        html = html.replace(/(["'`])((?:\\.|(?!\1)[^\\])*?)\1/g, '<span style="color: #ce9178;">$1$2$1</span>');

        // Comments
        html = html.replace(/\/\*[\s\S]*?\*\//g, '<span style="color: #6a9955;">$&</span>');
        html = html.replace(/\/\/.*$/gm, '<span style="color: #6a9955;">$&</span>');

        block.innerHTML = html;
    }

    function highlightBash(block) {
        let html = block.innerHTML;

        // Bash Keywords
        const bashKeywords = ['if', 'then', 'else', 'elif', 'fi', 'for', 'while', 'do', 'done', 'function', 'echo', 'git', 'npm', 'cd', 'ls', 'mkdir', 'rm', 'cp', 'mv'];
        bashKeywords.forEach(keyword => {
            const regex = new RegExp(`\\b${keyword}\\b`, 'g');
            html = html.replace(regex, `<span style="color: #569cd6;">${keyword}</span>`);
        });

        // Strings
        html = html.replace(/(["'])((?:\\.|(?!\1)[^\\])*?)\1/g, '<span style="color: #ce9178;">$1$2$1</span>');

        // Comments
        html = html.replace(/#.*$/gm, '<span style="color: #6a9955;">$&</span>');

        // Variables
        html = html.replace(/\$\w+/g, '<span style="color: #9cdcfe;">$&</span>');

        block.innerHTML = html;
    }

    // Aggiungi smooth scrolling per i link interni
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});
</script>
@endpush
