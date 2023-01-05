import React, {useState} from "react";

const TruncateText = ({text, maxLines = null}) => {
    const max = maxLines ?? 2;
    const lines = text.split("<br />");

    const [isTruncated, setIsTruncated] = useState(true);

    if (lines.length <= max)
        return <p dangerouslySetInnerHTML={{__html: text}}/>;
    return (
        <>
            <p
                className="text-break"
                dangerouslySetInnerHTML={{
                    __html: isTruncated
                        ? `${lines.splice(0, max).join("<br>")}`
                        : `${text}<br>`,
                }}
            />
            <button
                className="btn btn-link p-0"
                onClick={() => setIsTruncated(!isTruncated)}
            >
                {isTruncated ? "... Read more" : "Close"}
            </button>
        </>
    );
};

export default TruncateText;