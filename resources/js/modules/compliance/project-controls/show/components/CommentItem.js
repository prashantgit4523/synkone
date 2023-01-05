import React from "react";

import {usePage} from "@inertiajs/inertia-react";

import {diffForHumans} from "../Tabs/TasksTab";
import TruncateText from "./TruncateText";

const CommentItem = React.memo(({comment}) => {
    const {authUser} = usePage().props;
    return (
        <div className="comment-sec mb-2">
            <div className="comment-head d-flex align-items-center">
                <span className="avatar">{comment.sender?.avatar}</span>
                <h5 className="title m-2">
                    {comment.sender.id === authUser.id
                        ? "You"
                        : decodeHTMLEntity(comment.sender.full_name)}
                </h5>
                <small className="fw-bold my-s ms-auto time">
                    {diffForHumans(comment.created_at)}
                </small>
            </div>
            <div className="comment-body">
                <TruncateText text={comment.comment} maxLines={1}/>
            </div>
        </div>
    );
});

export default CommentItem;