import ReactMarkdown from 'react-markdown'
import remarkMath from 'remark-math'
import rehypeKatex from 'rehype-katex'
import 'katex/dist/katex.min.css'

export default function FormattedText({ children, className = '' }) {
  if (!children) return null
  
  return (
    <div className={`text-inherit ${className}`}>
      <ReactMarkdown
        remarkPlugins={[remarkMath]}
        rehypePlugins={[rehypeKatex]}
        components={{
          p: ({ node, ...props }) => <p className="mb-0 inline-block" {...props} />
        }}
      >
        {children}
      </ReactMarkdown>
    </div>
  )
}
